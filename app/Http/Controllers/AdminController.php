<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Response;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role']);
        $groups = Group::withCount('responses')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/dashboard', [
            'users' => $users,
            'groups' => $groups,
        ]);
    }

    /**
     * Create a new group.
     */
    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Group::create($validated);

        return back()->with('success', 'Group created successfully.');
    }

    /**
     * Update a user's role.
     */
    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:synthesis,drafter,admin',
        ]);

        $user->update($validated);

        return back()->with('success', 'User role updated successfully.');
    }

    /**
     * Assign a synthesis team member to a conversation.
     */
    public function assignSynthesis(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'synthesis_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['synthesis_id']);
        $conversation->synthesisMembers()->syncWithoutDetaching([$user->id]);

        return back()->with('success', "Synthesis member assigned to conversation {$conversation->title}.");
    }

    /**
     * Remove a synthesis team member from a conversation.
     */
    public function removeSynthesis(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'synthesis_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($validated['synthesis_id']);
        $conversation->synthesisMembers()->detach([$user->id]);

        return back()->with('success', "Synthesis member removed from conversation {$conversation->title}.");
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(Group $group)
    {
        $group->delete();

        return back()->with('success', 'Group deleted.');
    }

    /**
     * Display the admin chat page.
     */
    public function chat(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $selectedConversationId = $request->input('conversation');
        $conversations = Conversation::orderBy('created_at', 'desc')
            ->where('status', 'submitted')
            ->get(['id', 'title', 'status']);

        return inertia('admin/chat', [
            'conversations' => $conversations,
            'selectedConversationId' => $selectedConversationId ? (int) $selectedConversationId : null,
        ]);
    }

    /**
     * Return stats for selected conversation.
     */
    public function chatDetails(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $conversationId = $request->input('conversation_id');
        $conversation = Conversation::withCount(['groups as submitted_groups' => function ($q) {
            $q->whereHas('responses', function ($q2) {
                $q2->whereNotNull('consensus_text');
            });
        }])->find($conversationId);

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Get total groups for this conversation
        $totalGroups = $conversation->groups()->count();
        $submittedGroups = $conversation->submitted_groups ?? 0;

        // Get all submitted consensus responses
        $responses = Response::where('conversation_id', $conversationId)
            ->whereNotNull('consensus_text')
            ->get();

        return response()->json([
            'total_groups' => $totalGroups,
            'submitted_groups' => $submittedGroups,
            'responses' => $responses->map(fn($r) => [
                'id' => $r->id,
                'group_name' => $r->group->name,
                'consensus_text' => $r->consensus_text,
            ]),
        ]);
    }

    /**
     * Handle chat messages.
     */
    public function chatMessage(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string',
        ]);

        // Get all submitted consensus for this conversation
        $responses = Response::where('conversation_id', $validated['conversation_id'])
            ->whereNotNull('consensus_text')
            ->get();

        // Build context from all responses
        $context = $responses->map(function ($r) {
            return "## Group: {$r->group->name}\n{$r->consensus_text}";
        })->join("\n\n---\n\n");

        // Use LLM service to generate response
        $llmService = app(\App\Services\LlmService::class);
        $response = $llmService->generateChatResponse($validated['message'], $context);

        return response()->json([
            'reply' => $response,
            'conversation_id' => $validated['conversation_id'],
        ]);
    }
    public function generateKeyThemes(Request $request, LlmService $llmService)
    {
        // $this->authorizePastoralAccess($request);
        $conversation=Conversation::where("id",3)->with("responses")->get();
        

        $systemPrompt = "
            You are an objective and unbiased summarizer. The context of the texts that you receive here are consensus of group discussions. \n\n
            
            Your task is to go through the texts, and then come up with three key themes, which are the top three most repeated themes mentioned accross the group discussions.\n\n

            ====== Language and Tone ======\n\n
            Stick to original vocabulary used by the contents. \n\n

            Preserve the original meaning and intent of the texts.\n\n
            ====== End of Language and Tone ======\n\n

            ====== Output Format ====== \n\n
            Output in plain text, without markdown, or markdown code blocks. Use next lines instead to format your output, for example, for titles and subtitles. \n\n
            ====== End of Output Format ======\n\n

        ";
        $userPrompt = "====== Texts ======\n\n";
        $count=1;
        foreach ($conversation[0]->responses as $response) {
            $userPrompt .= "[{$count}]\n{$response->consensus_text}\n\n---\n\n";
                
                $count++;

        }
        $userPrompt.="====== End of Texts ======";

        $result = $llmService->query($systemPrompt, $userPrompt);
        if ($result['success']) {
            $generatedText = $result['content'];
            
        } else {
            return response()->json([
                "success"=>false,
                "message"=>$result['error']
            ], 200);
        }

        return response()->json([
                "success"=>true,
                "message"=>$generatedText
        ], 200);
    }

    public function generalPrompting(Request $request, LlmService $llmService)
    {
        // $this->authorizePastoralAccess($request);
        // $conversation=Conversation::where("id",3)->with("responses")->get();
        

        $systemPrompt = "
            Rearrange the given texts into coherent sentences or paragraphs.

            ====== Language and Tone ======\n\n
            Stick to original vocabulary used by the texts. \n\n

            Preserve the original meaning and intent of the texts.\n\n
            ====== End of Language and Tone ======\n\n

            ====== Output Format ====== \n\n
            Output in plain text, without markdown, or markdown code blocks. Use next lines instead to format your output, for example, for titles and subtitles. \n\n
            ====== End of Output Format ======\n\n

        ";
        $userPrompt = "====== Texts ======\n\n
            •	Requirement to train staff and pay market-value salaries for quality results
            •	concerns about the Church’s internal capacity to address complex issues such as mental health, broken families, and legal matters related to Islamisation, suggesting that individual dioceses or parishes lack the specialized resources to handle these alone
            •	duplication of efforts and the inefficiency of creating new structures when existing ministries like Caritas, SSVP, and Creation Justice Commissions remain underutilized or poorly coordinated.
            •	empowering and commissioning others, particularly youth, CARITAS, Creation Justice, and migrant ministries, to take greater responsibility and become agents of positive change.
            •	the need to cultivate moral integrity and ethical leadership to counteract issues of favoritism and corruption.
            •	the need for clearer stakeholder engagement strategies.
            •	tension in navigating the use of new technologies, such as AI, ensuring they support coordination without displacing the centrality of human relationships and mission.
            •	identifying actions that can be sustained together without overexerting resources , avoid burnout and acknowledging factors beyond our control
            •	limitations placed on the Malaysian people's voices and the injustices arising from Islamization, which challenge the Church’s ability to speak freely.
            •	the Church does not remain silent or fearful in the face of these complex socio-political dynamics.
            •	risk of programs becoming one-off events rather than long-term solutions.
            •	delicate balance required in navigating complex social and spiritual landscapes, where the safety and effectiveness of mission work depend on balancing moral integrity with practical wisdom.
            •	the tension between traditional structures and the need for new ways of engaging the younger generation, particularly in preparing them for political involvement and decision-making processes that affect the rakyat's well-being.
            •	Participants noted tensions around resource allocation, highlighting the need to reduce bureaucracy within the Church to allow better access to assistance and to prioritize spending on people rather than structures or beautification. There was also apprehension about the risk of burnout among volunteers and the need for adequate training budgets and professional support services.
        ====== End of Texts=====
        ";
        
        $result = $llmService->query($systemPrompt, $userPrompt);
        if ($result['success']) {
            $generatedText = $result['content'];
            
        } else {
            return response()->json([
                "success"=>false,
                "message"=>$result['error']
            ], 200);
        }

        return response()->json([
                "success"=>true,
                "message"=>$generatedText
        ], 200);
    }

}
