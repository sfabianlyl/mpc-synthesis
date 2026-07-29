<?php

namespace App\Http\Controllers;

use App\Broadcasting\Events\MasterSynthesisUpdated;
use App\Broadcasting\Events\SynthesisSubmitted;
use App\Jobs\LlmExtractionJob;
use App\Jobs\LlmSynthesisDraftJob;
use App\Jobs\LlmSynthesisSummaryJob;
use App\Jobs\ReportGenerationJob;
use App\Models\ConsensusNote;
use App\Models\Conversation;
use App\Models\Extraction;
use App\Models\Group;
use App\Models\MasterSynthesis;
use App\Models\Response;
use App\Models\SynthesisCustomQuery;
use App\Models\SynthesisDraft;
use App\Models\SynthesisGroupAssignment;
use App\Models\SynthesisKeyPoint;
use App\Models\SynthesisSummary;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SynthesisController extends Controller
{
    /**
     * Show the synthesis team dashboard with assigned conversations and their responses for the selected session.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all conversations (sessions) to populate select dropdown - filtered by assignment
        if ($user->isAdmin()) {
            $conversations = Conversation::orderBy('created_at', 'desc')->get(['id', 'title', 'status']);
        } else {
            $conversationIds = $user->assignedConversations()->pluck('conversations.id');
            $conversations = Conversation::whereIn('id', $conversationIds)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'title', 'status']);
        }

        if ($conversations->isEmpty()) {
            return Inertia::render('synthesis/dashboard', [
                'conversations' => [],
                'groups' => [],
                'report' => null,
                'selectedConversationId' => null,
                'consensusSubmissions' => [],
            ]);
        }

        // Determine which session is selected
        $selectedConversationId = $request->input('conversation_id');
        if (!$selectedConversationId) {
            $activeConv = $conversations->where('status', 'active')->first();
            $selectedConversationId = $activeConv ? $activeConv->id : $conversations->first()->id;
        }
        $selectedConversationId = (int) $selectedConversationId;

        // Validate user has access to selected conversation
        if (!$user->isAdmin()) {
            $allowedIds = $user->assignedConversations()->pluck('conversations.id');
            abort_unless($allowedIds->contains($selectedConversationId), 403, 'Access denied to this conversation.');
        }

        // Get all groups for the selected conversation (not just assigned groups)
        $allGroupsForConversation = Group::whereHas('responses', function ($query) use ($selectedConversationId) {
            $query->where('conversation_id', $selectedConversationId);
        })->with(['responses' => function ($query) use ($selectedConversationId) {
            $query->where('conversation_id', $selectedConversationId);
        }])->get();

        // Build groups structure with responses from ALL synthesis members assigned to this conversation
        $groups = $allGroupsForConversation->map(function ($group) {
            return (object)[
                'id' => $group->id,
                'name' => $group->name,
                'responses' => $group->responses,
            ];
        });

        // Fetch the synthesis report for this session and synthesis member
        $report = MasterSynthesis::where('conversation_id', $selectedConversationId)
            ->where('synthesis_id', $user->id)
            ->first();

        // Fetch consensus submission status from groups for the selected conversation
        $consensusSubmissions = [];
        if ($allGroupsForConversation->isNotEmpty()) {
            $groupIds = $allGroupsForConversation->pluck('id');
            $responses = Response::whereIn('group_id', $groupIds)
                ->where('conversation_id', $selectedConversationId)
                ->with('group')
                ->get();

            // Group submissions by group_id
            $consensusSubmissions = $responses->groupBy('group_id')->map(function ($groupResponses) {
                return $groupResponses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'group_id' => $response->group_id,
                        'group_name' => $response->group?->name,
                        'submitted_at' => $response->submitted_at?->toIso8601String(),
                        'consensus_text' => $response->consensus_text,
                        'is_submitted' => !is_null($response->submitted_at),
                    ];
                })->values()->toArray();
            })->toArray();
        }

        return Inertia::render('synthesis/dashboard', [
            'conversations' => $conversations,
            'groups' => $groups,
            'report' => $report,
            'selectedConversationId' => $selectedConversationId,
            'consensusSubmissions' => $consensusSubmissions,
        ]);
    }

    /**
     * Show the review/edit page for a specific session's synthesis report.
     */
    public function review(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Get assigned groups with their consensuses for this user & conversation
        $assignedGroups = Group::where('synthesis_id', $user->id)
            ->whereHas('conversations', fn($q) => $q->where('conversations.id', $conversation->id))
            ->with(['responses' => function ($query) use ($conversation) {
                $query->where('conversation_id', $conversation->id);
            }])
            ->get();

        // If no specific group synthesis_id filter matched, fetch all groups that have submitted responses for this conversation
        if ($assignedGroups->isEmpty()) {
            $assignedGroups = Group::whereHas('responses', function ($query) use ($conversation) {
                $query->where('conversation_id', $conversation->id);
            })->with(['responses' => function ($query) use ($conversation) {
                $query->where('conversation_id', $conversation->id);
            }])->get();
        }

        // Get user's notes for these groups
        $notes = ConsensusNote::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('group_id');

        // Get user's existing draft
        $draft = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        // Auto-create draft if none exists
        if (!$draft) {
            $draft = SynthesisDraft::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'status' => 'draft',
            ]);
        }

        // Get user's extraction (if any)
        $extraction = Extraction::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        // Check if at least one successful extraction exists (for disable logic)
        $hasSuccessfulExtraction = Extraction::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('is_successful', true)
            ->exists();

        // Check if all assigned groups have submitted their consensus
        $allSubmitted = $this->checkAllGroupsSubmitted($conversation->id, $user->id);

        // Fetch consensus submission status for assigned groups
        $consensusSubmissions = [];
        if ($assignedGroups->isNotEmpty()) {
            $groupIds = $assignedGroups->pluck('id');
            $responses = Response::whereIn('group_id', $groupIds)
                ->where('conversation_id', $conversation->id)
                ->with('group')
                ->get();

            $consensusSubmissions = $responses->groupBy('group_id')->map(function ($groupResponses) {
                return $groupResponses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'group_id' => $response->group_id,
                        'group_name' => $response->group?->name,
                        'submitted_at' => $response->submitted_at?->toIso8601String(),
                        'consensus_text' => $response->consensus_text,
                        'is_submitted' => !is_null($response->submitted_at),
                    ];
                })->values()->toArray();
            })->toArray();
        }

        return Inertia::render('synthesis/review', [
            'conversation' => $conversation,
            'assignedGroups' => $assignedGroups->map(function ($group) use ($consensusSubmissions, $notes) {
                return (object)[
                    'id' => $group->id,
                    'name' => $group->name,
                    'responses' => $group->responses,
                    'consensusSubmission' => $consensusSubmissions[$group->id][0] ?? null,
                    'note' => isset($notes[$group->id]) ? (object)[
                        'id' => $notes[$group->id]->id,
                        'text' => $notes[$group->id]->text,
                    ] : null,
                ];
            })->values()->toArray(),
            'notes' => $notes->map(fn($n) => (object)[
                'id' => $n->id,
                'group_id' => $n->group_id,
                'text' => $n->text,
            ])->toArray(),
            'draft' => $draft ? [
                'id' => $draft->id,
                'convergence_text' => $draft->convergence_text,
                'consideration_text' => $draft->consideration_text,
                'proposals_text' => $draft->proposals_text,
                'other_text' => $draft->other_text,
                'is_submitted' => $draft->is_submitted,
                'submitted_at' => $draft->submitted_at?->toIso8601String(),
            ] : null,
            'extraction' => $extraction ? [
                'id' => $extraction->id,
                'convergence_text' => $extraction->convergence_text,
                'consideration_text' => $extraction->consideration_text,
                'proposals_text' => $extraction->proposals_text,
                'provider' => $extraction->provider,
                'is_successful' => $extraction->is_successful,
                'extracted_at' => $extraction->extracted_at?->toIso8601String(),
            ] : null,
            'allConsensusSubmitted' => $allSubmitted,
            'consensusSubmissions' => $consensusSubmissions,
            'canTrigger' => !$hasSuccessfulExtraction,
        ]);
    }

    /**
     * Trigger LLM analysis for selected responses of groups in a session.
     */
    public function analyze(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Check if user has any assigned conversations
        if ($user->assignedConversations()->get()->isEmpty()) {
            return back()->withErrors(['analysis' => 'No conversations are assigned to you.']);
        }

        // Validate selected_group_ids if provided
        $validated = $request->validate([
            'selected_group_ids' => 'nullable|array',
            'selected_group_ids.*' => 'exists:groups,id',
        ]);

        // Get selected group IDs from request
        $selectedGroupIds = $validated['selected_group_ids'] ?? null;

        // Collect responses, filtered by selected groups if provided
        if ($selectedGroupIds && count($selectedGroupIds) > 0) {
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', $selectedGroupIds)
                ->with('group')
                ->get();
        } else {
            // Get all responses from the selected conversation that belong to groups in assigned conversations
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', function ($query) use ($selectedGroupIds, $user) {
                    if ($selectedGroupIds && count($selectedGroupIds) > 0) {
                        $query->select('group_id')
                            ->from('responses')
                            ->whereIn('conversation_id', $user->assignedConversations()->pluck('conversations.id'))
                            ->whereIn('group_id', $selectedGroupIds)
                            ->distinct();
                    } else {
                        $query->select('group_id')
                            ->from('responses')
                            ->whereIn('conversation_id', $user->assignedConversations()->pluck('conversations.id'))
                            ->distinct();
                    }
                })
                ->with('group')
                ->get();
        }

        if ($responses->isEmpty()) {
            return back()->withErrors(['analysis' => 'No responses have been submitted by your assigned groups for this session.']);
        }

        // Dispatch queued extraction job instead of inline LLM call
        LlmExtractionJob::dispatch($conversation->id, $user->id, $selectedGroupIds);

        return back()->with([
            'success' => 'AI extraction started. Results will appear shortly.',
        ]);
    }

    /**
     * Generate AI-assisted synthesis for notes (summarizes user ConsensusNotes into SynthesisDraft).
     */
    public function generateAiAssistedSynthesis(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Dispatch background synthesis job to summarize consensus notes into user draft
        LlmSynthesisDraftJob::dispatch($conversation->id, $user->id);

        return back()->with([
            'success' => 'AI synthesis from notes started. Your draft will update shortly.',
        ]);
    }

    /**
     * Handle a custom LLM query from the synthesis review page.
     * 
     * The synthesis member can use custom prompts for synthesis focus areas not covered by the 3 main categories.
     * The responses cannot be edited, but can be deleted.
     */
    public function customQuery(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization (same pattern as analyze)
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'selected_group_ids' => 'nullable|array',
            'selected_group_ids.*' => 'exists:groups,id',
        ]);

        // Ensure report exists
        $report = MasterSynthesis::firstOrCreate(
            ['conversation_id' => $conversation->id, 'synthesis_id' => $user->id],
            ['status' => 'draft']
        );

        // Gather responses (same logic as analyze)
        $selectedGroupIds = $validated['selected_group_ids'] ?? null;
        if ($selectedGroupIds && count($selectedGroupIds) > 0) {
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', $selectedGroupIds)->with('group')->get();
        } else {
            $responses = Response::where('conversation_id', $conversation->id)
                ->with('group')->get();
        }

        // For custom queries, we still need the LLM service - keep as-is for now
        // This would require injecting LlmService or using a different approach
        return back()->with([
            'success' => 'Custom query feature requires LLM service integration.',
        ]);
    }

    /**
     * Delete a custom LLM query.
     * Custom query responses cannot be edited, but can be deleted.
     */
    public function deleteCustomQuery(Request $request, SynthesisCustomQuery $query)
    {
        $user = $request->user();
        $report = $query->synthesisReport;

        if (!$user->isAdmin() && $report->synthesis_id !== $user->id) {
            abort(403, 'You do not have access to this query.');
        }

        $query->delete();

        return back()->with('success', 'Custom query deleted.');
    }

    /**
     * Update a synthesis report (manual edits).
     */
    public function update(Request $request, MasterSynthesis $report)
    {
        $user = $request->user();

        // Authorization: check if user owns the report or is admin, OR is assigned to the conversation
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && $report->synthesis_id !== $user->id && !$conversationIds->contains($report->conversation_id)) {
            abort(403, 'You do not have access to this report.');
        }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
            'status' => 'nullable|in:draft,reviewed,submitted',
        ]);

        $report->update($validated);

        return back()->with('success', 'Report updated successfully.');
    }

    /**
     * Submit the synthesis report for compilation.
     */
    public function submit(Request $request, MasterSynthesis $report)
    {
        $user = $request->user();

        // Authorization: check if user owns the report or is admin, OR is assigned to the conversation
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && $report->synthesis_id !== $user->id && !$conversationIds->contains($report->conversation_id)) {
            abort(403, 'You do not have access to this report.');
        }

        $report->update(['status' => 'submitted']);

        return redirect()->route('synthesis.dashboard')->with('success', 'Report submitted for compilation.');
    }

    /**
     * Show the collaborative review page with all submissions grouped by category.
     */
    public function reviewPage(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Fetch ALL groups for this conversation
        $allGroupsForConversation = Group::whereHas('responses', function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        })->with(['responses' => function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        }])->get();

        // Build groups structure
        $groups = $allGroupsForConversation->map(function ($group) {
            return (object)[
                'id' => $group->id,
                'name' => $group->name,
                'responses' => $group->responses,
            ];
        });

        // Fetch all submitted SynthesisDraft records for this conversation
        $drafts = SynthesisDraft::where('conversation_id', $conversation->id)
            ->with('user')
            ->orderBy('updated_at')
            ->get();

        $submissions = $drafts->map(function ($d) {
            return [
                'id' => $d->id,
                'submission_number' => 1,
                'user' => [
                    'id' => $d->user->id,
                    'name' => $d->user->name,
                    'email' => $d->user->email,
                ],
                'convergence_text' => $d->convergence_text,
                'consideration_text' => $d->consideration_text,
                'proposals_text' => $d->proposals_text,
                'other_text' => $d->other_text,
                'status' => $d->is_submitted ? 'submitted' : 'draft',
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'custom_queries' => [],
            ];
        });

        // Fetch master synthesis (latest submitted report for this conversation)
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->whereIn('status', ['submitted', 'reviewed'])
            ->orderBy('submission_number', 'desc')
            ->first();

        $masterSynthesisData = $masterSynthesis ? [
            'id' => $masterSynthesis->id,
            'convergence_text' => $masterSynthesis->convergence_text,
            'consideration_text' => $masterSynthesis->consideration_text,
            'proposals_text' => $masterSynthesis->proposals_text,
            'other_text' => $masterSynthesis->other_text,
            'status' => $masterSynthesis->status,
        ] : null;

        // Fetch consensus submission status from all groups for this conversation
        $consensusSubmissions = [];
        if ($allGroupsForConversation->isNotEmpty()) {
            $groupIds = $allGroupsForConversation->pluck('id');
            $responses = Response::whereIn('group_id', $groupIds)
                ->where('conversation_id', $conversation->id)
                ->with('group')
                ->get();

            $consensusSubmissions = $responses->groupBy('group_id')->map(function ($groupResponses) {
                return $groupResponses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'group_id' => $response->group_id,
                        'group_name' => $response->group?->name,
                        'submitted_at' => $response->submitted_at?->toIso8601String(),
                        'consensus_text' => $response->consensus_text,
                        'is_submitted' => !is_null($response->submitted_at),
                    ];
                })->values()->toArray();
            })->toArray();
        }

        return Inertia::render('synthesis/collaborate', [
            'conversation' => $conversation,
            'submissions' => $submissions,
            'masterSynthesis' => $masterSynthesisData,
            'groups' => $groups,
            'responses' => $groups->flatMap->responses,
            'consensusSubmissions' => $consensusSubmissions,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Submit a new synthesis for the conversation.
     * 
     * The submission comprises:
     * - Manual inputs by category (convergence_text, consideration_text, proposals_text, other_text)
     * 
     * After submission, all inputs are cleared so the synthesis member can begin again.
     */
    public function submitSynthesis(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        // Check if user already has a submitted submission for this conversation
        $existingSubmitted = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('status', 'submitted')
            ->first();

        if ($existingSubmitted) {
            return back()->withErrors([
                'submission' => 'You have already submitted your synthesis for this conversation. Please start a new submission cycle.',
            ]);
        }

        // Find the existing draft to get its ID for custom query cleanup
        $existingDraft = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft) {
            // Update existing draft with all data (manual inputs)
            $updateData = array_merge($validated, [
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            $existingDraft->update($updateData);
            $report = $existingDraft->fresh();
        } else {
            // Create new submission with next submission_number
            $nextNumber = MasterSynthesis::getNextSubmissionNumber($conversation->id, $user->id);
            $createData = array_merge($validated, [
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => $nextNumber,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            $report = MasterSynthesis::create($createData);
        }

        // Broadcast the submission event for real-time updates
        event(new SynthesisSubmitted($conversation->id, $report));

        // Return success with flag to clear inputs on frontend
        return back()->with([
            'success' => 'Synthesis submitted successfully.',
            'synthesis_submitted' => true,
        ]);
    }

    /**
     * Store a master synthesis report for the conversation.
     * 
     * Master synthesis represents the "synthesis of synthesis" crafted collaboratively during
     * the Collaborative Review session. It requires all 4 categories to have content.
     * Only users with synthesis role can create master syntheses.
     */
    public function storeMasterSynthesis(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: only users with synthesis/drafter/admin role can create master syntheses
        if (!$user->isAdmin() && $user->role !== 'drafter') {
            abort(403, 'You do not have permission to create master syntheses.');
        }

        // Validate all 4 categories have content (manual only)
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.convergence' => 'required|array',
            'categories.convergence.manual_content' => 'nullable|string',
            'categories.matters_for_consideration' => 'required|array',
            'categories.matters_for_consideration.manual_content' => 'nullable|string',
            'categories.proposals' => 'required|array',
            'categories.proposals.manual_content' => 'nullable|string',
            'categories.others' => 'required|array',
            'categories.others.manual_content' => 'nullable|string',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer',
        ]);

        // Ensure each category has content
        foreach (['convergence', 'matters_for_consideration', 'proposals', 'others'] as $category) {
            $manualContent = $validated['categories'][$category]['manual_content'] ?? '';
            if (empty($manualContent)) {
                return back()->withErrors([
                    "categories.{$category}" => "The {$category} category must have content.",
                ]);
            }
        }

        // Find or create the master synthesis report for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => MasterSynthesis::getNextSubmissionNumber($conversation->id, $user->id),
                'is_master_synthesis' => true,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        // Map category names to database field names (manual content only)
        $categoryMap = [
            'convergence' => 'convergence_text',
            'matters_for_consideration' => 'consideration_text',
            'proposals' => 'proposals_text',
            'others' => 'other_text',
        ];

        $updateData = [];

        foreach ($categoryMap as $categoryKey => $field) {
            $manualContent = $validated['categories'][$categoryKey]['manual_content'] ?? '';
            if (!empty($manualContent)) {
                $updateData[$field] = $manualContent;
            }
        }

        // Track which group consensuses were included
        if (isset($validated['group_ids']) && !empty($validated['group_ids'])) {
            $updateData['group_ids'] = $validated['group_ids'];
        }

        $report->update($updateData);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return response()->json([
            'success' => true,
            'master_synthesis' => [
                'id' => $report->id,
                'conversation_id' => $report->conversation_id,
                'is_master_synthesis' => $report->is_master_synthesis,
                'categories' => [
                    'convergence' => ['manual_content' => $report->convergence_text ?? ''],
                    'matters_for_consideration' => ['manual_content' => $report->consideration_text ?? ''],
                    'proposals' => ['manual_content' => $report->proposals_text ?? ''],
                    'others' => ['manual_content' => $report->other_text ?? ''],
                ],
                'group_ids' => $report->group_ids ?? [],
                'status' => $report->status,
                'submitted_at' => $report->submitted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the master synthesis for a given conversation.
     * Returns null or empty structure if no master synthesis exists.
     */
    public function getMasterSynthesis(Conversation $conversation)
    {
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        if (!$masterSynthesis) {
            return response()->json(['master_synthesis' => null, 'has_master_synthesis' => false]);
        }

        return response()->json([
            'master_synthesis' => [
                'id' => $masterSynthesis->id,
                'conversation_id' => $masterSynthesis->conversation_id,
                'user_id' => $masterSynthesis->synthesis_id,
                'submission_number' => $masterSynthesis->submission_number,
                'is_master_synthesis' => $masterSynthesis->is_master_synthesis,
                'group_ids' => $masterSynthesis->group_ids ?? [],
                'submitted_at' => $masterSynthesis->submitted_at?->toIso8601String(),
                'categories' => [
                    'convergence' => ['manual_content' => $masterSynthesis->convergence_text ?? ''],
                    'matters_for_consideration' => ['manual_content' => $masterSynthesis->consideration_text ?? ''],
                    'proposals' => ['manual_content' => $masterSynthesis->proposals_text ?? ''],
                    'others' => ['manual_content' => $masterSynthesis->other_text ?? ''],
                ],
                'status' => $masterSynthesis->status,
            ],
            'has_master_synthesis' => true,
        ]);
    }

    /**
     * Get all syntheses (individual + master) grouped by category for a conversation.
     * Distinguishes between individual syntheses and master syntheses.
     */
    public function getSynthesesByCategory(Conversation $conversation)
    {
        // Fetch individual syntheses (non-master)
        $individualSyntheses = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', false)
            ->with('synthesis')
            ->orderBy('submission_number')
            ->get();

        // Fetch master synthesis (if exists)
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        $fields = [
            'convergence' => 'convergence_text',
            'matters_for_consideration' => 'consideration_text',
            'proposals' => 'proposals_text',
            'others' => 'other_text',
        ];

        $categories = [];
        foreach ($fields as $catKey => $catField) {
            $categories[$catKey] = [
                'individual_syntheses' => $individualSyntheses->map(fn($s) => [
                    'id' => $s->id,
                    'user_id' => $s->synthesis_id,
                    'user_name' => $s->synthesis?->name,
                    'submission_number' => $s->submission_number,
                    'manual_content' => $s->{$catField} ?? '',
                    'status' => $s->status,
                    'submitted_at' => $s->submitted_at?->toIso8601String(),
                ])->toArray(),
                'master_synthesis' => $masterSynthesis ? [
                    'id' => $masterSynthesis->id,
                    'user_id' => $masterSynthesis->synthesis_id,
                    'manual_content' => $masterSynthesis->{$catField} ?? '',
                    'submitted_at' => $masterSynthesis->submitted_at?->toIso8601String(),
                ] : null,
            ];
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'categories' => $categories,
            'individual_synthesis_count' => $individualSyntheses->count(),
            'has_master_synthesis' => $masterSynthesis !== null,
        ]);
    }

    /**
     * Update the master synthesis report (for drafter/admin role).
     */
    public function updateMasterSynthesis(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Only allow drafter or admin to update master synthesis
        if (!$user->isAdmin() && $user->role !== 'drafter') {
            abort(403, 'You do not have permission to update the master synthesis.');
        }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        // Find or create the master synthesis report for this conversation.
        // Match records that are master synthesis (is_master_synthesis = true) to avoid
        // accidentally updating a regular user's submitted report.
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->whereIn('status', ['submitted', 'reviewed'])
            ->first();

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => MasterSynthesis::getNextSubmissionNumber($conversation->id, $user->id),
                'is_master_synthesis' => true,
                'status' => 'submitted',
            ]);
        }

        $report->update($validated);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return back()->with('success', 'Master synthesis updated successfully.');
    }

    /**
     * Export the current user's synthesis report as a booklet PDF.
     */
    public function exportBooklet(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Get the user's submitted synthesis report for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('status', 'submitted')
            ->first();

        if (!$report) {
            return back()->withErrors(['export' => 'No submitted synthesis report available for export.']);
        }

        // Wrap the single report in a collection compatible with the existing PDF template
        $reports = collect([$report]);

        $document = (object)[
            'title' => $conversation->title . ' — Synthesis Report',
            'content_json' => null,
            'emerging_themes' => null,
        ];

        $html = view('pdf.synthesis-booklet', [
            'document' => $document,
            'reports' => $reports,
        ])->render();

        $filename = 'synthesis-booklet-' . $conversation->id . '-' . now()->format('Y-m-d') . '.pdf';

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.html"');
    }

    // ─── New Methods for Synthesis Review Refactoring ─────────────────

    /**
     * Get groups assigned to current user for a conversation.
     */
    public function getGroupAssignments(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $assignments = Group::where('synthesis_id', $user->id)
            ->whereHas('conversations', fn($q) => $q->where('conversations.id', $conversation->id))
            ->get();

        return response()->json([
            'assignments' => $assignments->map(fn($g) => [
                'id' => $g->id,
                'group_id' => $g->id,
                'group_name' => $g->name,
            ]),
        ]);
    }

    /**
     * Save/update notes for a group consensus.
     */
    public function saveConsensusNote(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'notes' => 'nullable|string',
        ]);

        ConsensusNote::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'group_id' => $validated['group_id'],
            ],
            ['text' => $validated['notes'] ?? null]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Save/update notes for all groups in one batch request.
     * Accepts an array of { group_id, notes } objects and upserts each.
     * Returns 204 No Content so the browser doesn't display a JSON popup.
     */
    public function saveBatchConsensusNotes(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.group_id' => 'required|exists:groups,id',
            'notes.*.text' => 'nullable|string',
        ]);

        foreach ($validated['notes'] as $item) {
            ConsensusNote::updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'group_id' => $item['group_id'],
                ],
                ['text' => $item['text'] ?? null]
            );
        }

        return response()->noContent();
    }


    /**
     * Get all notes for current user's assigned groups.
     */
    public function getConsensusNotes(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $notes = ConsensusNote::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'notes' => $notes->map(fn($n) => [
                'id' => $n->id,
                'group_id' => $n->group_id,
                'text' => $n->text,
            ]),
        ]);
    }

    /**
     * Manually trigger LLM extraction (queued job).
     */
    public function triggerExtraction(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Check backend lock: if all assigned groups submitted AND successful extraction exists -> block manual trigger
        if ($this->checkAllGroupsSubmitted($conversation->id, $user->id)) {
            $hasExtraction = Extraction::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->where('is_successful', true)
                ->exists();

            if ($hasExtraction) {
                return response()->json(['success' => false, 'message' => 'Manual extraction disabled as a successful extraction already exists.'], 422);
            }
        }

        LlmExtractionJob::dispatch($conversation->id, $user->id);

        return response()->json(['success' => true, 'message' => 'Extraction job dispatched.']);
    }

    /**
     * Get extraction for current user.
     */
    public function getExtractions(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $extraction = Extraction::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'extraction' => $extraction ? [
                'id' => $extraction->id,
                'convergence_text' => $extraction->convergence_text,
                'consideration_text' => $extraction->consideration_text,
                'proposals_text' => $extraction->proposals_text,
                'provider' => $extraction->provider,
                'is_successful' => $extraction->is_successful,
                'extracted_at' => $extraction->extracted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Autosave draft content.
     */
    public function saveDraft(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        $draft = SynthesisDraft::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            $validated
        );

        return response()->json(['success' => true, 'draft_id' => $draft->id]);
    }

    /**
     * Submit synthesis draft and redirect to collaborate page.
     */
    public function submitDraft(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $draft = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$draft || $draft->is_submitted) {
            return back()->withErrors(['submission' => 'No draft to submit or already submitted.']);
        }

        // Mark draft as submitted
        $draft->update([
            'is_submitted' => true,
            'submitted_at' => now(),
        ]);

        // Sync or create corresponding MasterSynthesis submission so it appears on collaborate.tsx
        $masterReport = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('is_master_synthesis', false)
            ->first();

        if ($masterReport) {
            $masterReport->update([
                'convergence_text' => $draft->convergence_text,
                'consideration_text' => $draft->consideration_text,
                'proposals_text' => $draft->proposals_text,
                'other_text' => $draft->other_text,
                'status' => 'submitted',
                'is_submitted' => true,
                'submitted_at' => now(),
            ]);
        } else {
            $nextNumber = MasterSynthesis::getNextSubmissionNumber($conversation->id, $user->id);
            $masterReport = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => $nextNumber,
                'convergence_text' => $draft->convergence_text,
                'consideration_text' => $draft->consideration_text,
                'proposals_text' => $draft->proposals_text,
                'other_text' => $draft->other_text,
                'is_master_synthesis' => false,
                'status' => 'submitted',
                'is_submitted' => true,
                'submitted_at' => now(),
            ]);
        }

        event(new SynthesisSubmitted($conversation->id, $masterReport));

        // Auto-trigger summary check: if all assigned members have submitted, dispatch summary job
        $this->checkAndAutoTriggerSummary($request, $conversation);

        // Redirect to collaborative review page
        return redirect()->route('synthesis.collaborate', ['conversation' => $conversation])->with('success', 'Synthesis submitted successfully.');
    }

    /**
     * Check if all assigned groups have submitted their consensus.
     */
    private function checkAllGroupsSubmitted(int $conversationId, int $userId): bool
    {
        $assignedGroupIds = Group::where('synthesis_id', $userId)
            ->whereHas('conversations', fn($q) => $q->where('conversations.id', $conversationId))
            ->pluck('id');

        if ($assignedGroupIds->isEmpty()) {
            return false;
        }

        $submittedCount = Response::where('conversation_id', $conversationId)
            ->whereIn('group_id', $assignedGroupIds)
            ->whereNotNull('submitted_at')
            ->count();

        return $submittedCount >= $assignedGroupIds->count();
    }

    // ─── Phase 2: Synthesis Flow Adjustments ─────────────────

    /**
     * Task 1: Check if manual extraction trigger should be disabled.
     * Returns true if at least one successful extraction already exists for this master synthesis.
     */
    public function canTriggerExtraction(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Check if at least one successful extraction exists for this conversation/user
        $hasSuccessfulExtraction = Extraction::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('is_successful', true)
            ->exists();

        return response()->json(['can_trigger' => !$hasSuccessfulExtraction]);
    }

    /**
     * Task 3a: Manually trigger synthesis summary generation for a master synthesis.
     */
    public function triggerSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Check backend lock: if all assigned members submitted drafts AND a successful summary exists -> block manual trigger
        $assignedUserIds = Conversation::find($conversation->id)?->synthesisMembers()->pluck('users.id') ?? collect();
        if ($assignedUserIds->isNotEmpty()) {
            $submittedCount = SynthesisDraft::where('conversation_id', $conversation->id)
                ->whereIn('user_id', $assignedUserIds)
                ->where('is_submitted', true)
                ->count();

            if ($submittedCount >= $assignedUserIds->count()) {
                $hasSummary = SynthesisSummary::where('conversation_id', $conversation->id)
                    ->where('is_successful', true)
                    ->exists();

                if ($hasSummary) {
                    return response()->json(['success' => false, 'message' => 'Manual summary disabled as a successful summary already exists.'], 422);
                }
            }
        }

        // Dispatch the LlmSynthesisSummaryJob
        LlmSynthesisSummaryJob::dispatch($conversation->id);

        return response()->json(['success' => true, 'message' => 'Summary generation job dispatched.']);
    }

    /**
     * Task 3a: Auto-trigger summary when all assigned members have submitted their drafts.
     * Call this method after a SynthesisDraft is successfully submitted.
     */
    public function checkAndAutoTriggerSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Get all users assigned to this conversation
        $assignedUserIds = $conversation->synthesisMembers()->pluck('users.id');

        if ($assignedUserIds->isEmpty()) {
            return response()->json(['triggered' => false, 'reason' => 'No assigned users']);
        }

        // Check if all assigned users have submitted their drafts
        $submittedCount = SynthesisDraft::where('conversation_id', $conversation->id)
            ->whereIn('user_id', $assignedUserIds)
            ->where('is_submitted', true)
            ->count();

        if ($submittedCount < $assignedUserIds->count()) {
            return response()->json(['triggered' => false, 'reason' => 'Not all users have submitted']);
        }

        // All users submitted - auto-dispatch summary job
        LlmSynthesisSummaryJob::dispatch($conversation->id);

        return response()->json(['triggered' => true]);
    }

    /**
     * Task 4: Update authorization to allow all assigned members to edit MasterSynthesis.
     * Updated updateMasterSynthesis method with new authorization logic.
     */
    public function updateMasterSynthesisAuthorized(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is admin OR is assigned to this conversation
        $isAdmin = $user->isAdmin();
        $isAssigned = $conversation->synthesisMembers()->where('users.id', $user->id)->exists();

        if (!$isAdmin && !$isAssigned) {
            abort(403, 'You do not have permission to update the master synthesis.');
        }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        // Find or create the master synthesis report for this conversation.
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        if ($report && $report->is_submitted) {
            return back()->withErrors(['master_synthesis' => 'Master synthesis is locked and cannot be edited.']);
        }

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => MasterSynthesis::getNextSubmissionNumber($conversation->id, $user->id),
                'is_master_synthesis' => true,
                'status' => 'submitted',
            ]);
        }

        $report->update($validated);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return back()->with('success', 'Master synthesis updated successfully.');
    }

    /**
     * Task 5: Submit the master synthesis (lock it and dispatch report generation).
     */
    public function submitMasterSynthesis(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is admin OR is assigned to this conversation
        $isAdmin = $user->isAdmin();
        $isAssigned = $conversation->synthesisMembers()->where('users.id', $user->id)->exists();

        if (!$isAdmin && !$isAssigned) {
            abort(403, 'You do not have permission to submit the master synthesis.');
        }

        // Find the master synthesis for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        if (!$report) {
            return back()->withErrors(['submission' => 'No master synthesis found to submit.']);
        }

        // Check if already submitted
        if ($report->is_submitted) {
            return back()->withErrors(['submission' => 'This master synthesis has already been submitted.']);
        }

        // Set is_submitted = true and lock the master synthesis
        $report->update([
            'is_submitted' => true,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Dispatch ReportGenerationJob
        ReportGenerationJob::dispatch($report);

        return redirect()->route('synthesis.collaborate', ['conversation' => $conversation])->with('success', 'Master synthesis submitted successfully. Report generation has started.');
    }

    /**
     * Extract key points from master synthesis using LLM.
     */
    public function extractKeyPoints(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'max_key_points' => 'required|integer|min:1|max:10',
            'master_synthesis_id' => 'required|integer',
        ]);

        // Get the master synthesis
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->findOrFail($validated['master_synthesis_id']);

        // Combine all master synthesis content
        $synthesisContent = collect([
            'Convergence: ' . ($masterSynthesis->convergence_text ?? ''),
            'Matters for Consideration: ' . ($masterSynthesis->consideration_text ?? ''),
            'Proposals: ' . ($masterSynthesis->proposals_text ?? ''),
            'Others: ' . ($masterSynthesis->other_text ?? ''),
        ])->filter()->values()->implode("\n\n");

        if (empty(trim($synthesisContent))) {
            return response()->json([
                'success' => false,
                'message' => 'No master synthesis content available to extract key points from.',
            ], 422);
        }

        // Call LLM service to extract key points
        $llmService = app(LlmService::class);
        $result = $llmService->extractKeyPoints($synthesisContent, $validated['max_key_points']);

        // Parse the response into bullet points
        $keyPointsText = $result['content'];
        $keyPointsArray = array_filter(
            array_map('trim', explode("\n", $keyPointsText)),
            fn($line) => str_starts_with($line, '-')
        );

        // Store the key points in the database
        $synthesisKeyPoint = SynthesisKeyPoint::create([
            'conversation_id' => $conversation->id,
            'master_synthesis_id' => $masterSynthesis->id,
            'user_id' => $user->id,
            'source_category' => 'all',
            'key_points_text' => $keyPointsText,
            'key_points' => array_values($keyPointsArray),
            'requested_count' => $validated['max_key_points'],
            'provider' => $result['provider'],
            'status' => $result['success'] ? 'completed' : 'failed',
            'completed_at' => $result['success'] ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'key_points' => [
                'id' => $synthesisKeyPoint->id,
                'key_points_text' => $synthesisKeyPoint->key_points_text,
                'key_points' => $synthesisKeyPoint->key_points ?? [],
                'requested_count' => $synthesisKeyPoint->requested_count,
                'provider' => $synthesisKeyPoint->provider,
            ],
        ]);
    }

    /**
     * Get key points for a conversation.
     */
    public function getKeyPoints(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $keyPoints = SynthesisKeyPoint::where('conversation_id', $conversation->id)
            ->where('master_synthesis_id', $request->input('master_synthesis_id'))
            ->completed()
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'key_point' => $keyPoints ? [
                'id' => $keyPoints->id,
                'key_points_text' => $keyPoints->key_points_text,
                'key_points' => $keyPoints->key_points ?? [],
                'requested_count' => $keyPoints->requested_count,
                'provider' => $keyPoints->provider,
                'created_at' => $keyPoints->created_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
