<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\NationalDecisionDraft;
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
        $collection=NationalDecisionDraft::get();
        $column="draft_text";

        $systemPrompt = "
            You are an objective and unbiased summarizer. The context of the texts that you receive here are notes. \n\n
            
            Your task is to go through the texts, and then come up with three most reoccuring themes.\n\n

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
        foreach ($collection as $object) {
            $userPrompt .= "[{$count}]\n{$object->$column}\n\n---\n\n";
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
            •	Members emphasized the importance of gotong royong, community visitation, and fellowship as essential methods for building bridges, focusing on what unites us, and fostering a sense of belonging among all people.
            •	Participants widely agreed that this preparation requires a holistic approach, combining faith formation with academic education to ensure the youth are equipped for leadership.
            •	empowering the youth and lay faithful through education and spiritual formation, ensuring they are equipped to carry out ministries and take responsibility for their mission.
            •	support of programs that care for the environment, educate migrants, and support youth and children in interior regions
            •	necessity for dynamic and innovative formation programs for children and youth, such as anthropological approaches to catechism and involving them in church outreach activities.
            •	providing unbiased, accessible, and holistic education, particularly for the poor
            •	the importance of education and the enhancement of Faith Formation and Catholic Education, alongside the view that young people should act as digital missionaries.
            •	integrating Catholic Social Teaching into daily life rather than keeping it as theoretical knowledge.
            •	need for consistent awareness and accountability to ensure that pledges, such as those under Laudato Si’, are not just symbolic but lived out in practice.
            •	necessity of equipping leaders to serve beyond the Catholic community and the challenge of ensuring that nation-building efforts begin today rather than being deferred to tomorrow.
            •	having more educational institutions under the Catholic Church while carefully considering the affordability for Catholic parents to send their children to these schools.
            •	there is a critical requirement for transparency, as the Bishops, Priests, and Parish Pastoral Councils must ensure that financial management and the appropriate utilization of funds for pastoral development are fully disclosed to the Faithful to maintain the Church's upright and trustworthy public image.
            •	enforcement of ecological laws and the need for deeper knowledge on policies and laws.
            •	utilizing the See-Judge-Act method to translate discernment into action
            •	pull resources to support one another in addressing specific needs such as Islamisation, mental health issues, broken families, and the needs of youth, including providing Catholic lawyers in cases of conversion.
            •	seminary formation must include social and political science to better equip the clergy to understand the nation's current situation and speak the truth without fear.
            •	establish the first Catholic University in East Malaysia
            •	conducting training and advocacy programs, and integrating social doctrine and environmental stewardship into homilies and parish programs.
            •	join with the Government and NGOs regarding environmental issues, educate politicians and businesses on preserving Mother Earth, and empower young people for mission in society and as stewards of the Earth.
            •	actionable ideas included mobilizing the Migrant Desk to assist stateless children, exercising voting rights to choose leaders with good stewardship, and launching recycling and disaster relief efforts.
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
