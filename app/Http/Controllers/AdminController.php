<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Response;
use App\Models\User;
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
}
