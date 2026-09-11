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
        $conversation=Conversation::where("id",1)->with("responses")->get();
        

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

}
