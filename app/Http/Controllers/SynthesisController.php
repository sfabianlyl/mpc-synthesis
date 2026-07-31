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

        // Fetch SynthesisSummary for this conversation
        $synthesisSummary = SynthesisSummary::where('conversation_id', $conversation->id)->first();

        $synthesisSummaryData = $synthesisSummary ? [
            'id' => $synthesisSummary->id,
            'convergence_summary' => $synthesisSummary->convergence_summary,
            'consideration_summary' => $synthesisSummary->consideration_summary,
            'proposals_summary' => $synthesisSummary->proposals_summary,
            'other_summary' => $synthesisSummary->other_summary,
            'status' => $synthesisSummary->status,
            'is_successful' => $synthesisSummary->is_successful,
            'provider' => $synthesisSummary->provider,
            'generated_at' => $synthesisSummary->generated_at?->toIso8601String(),
        ] : null;

        return Inertia::render('synthesis/collaborate', [
            'conversation' => $conversation,
            'submissions' => $submissions,
            'masterSynthesis' => $masterSynthesisData,
            'synthesisSummary' => $synthesisSummaryData,
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

        // Fetch key points for this conversation
        $keyPoints = SynthesisKeyPoint::where('conversation_id', $conversation->id)
            ->completed()
            ->orderByDesc('created_at')
            ->get();

        $html = view('pdf.synthesis-booklet', [
            'document' => $document,
            'reports' => $reports,
            'keyPoints' => $keyPoints,
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
     * Synchronous LLM extraction — returns parsed JSON directly for immediate display.
     */
    public function extractSync(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Get all group consensuses for this user's assigned groups
        $assignedGroupIds = \App\Models\Group::where('synthesis_id', $user->id)
            ->whereHas('conversations', fn($q) => $q->where('conversations.id', $conversation->id))
            ->pluck('id');

        if ($assignedGroupIds->isEmpty()) {
            $assignedGroupIds = Response::where('conversation_id', $conversation->id)
                ->whereNotNull('submitted_at')
                ->pluck('group_id')
                ->unique();
        }

        // Fetch consensus texts from submitted responses and consensus notes
        $responseConsensusTexts = Response::whereIn('group_id', $assignedGroupIds)
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('submitted_at')
            ->pluck('consensus_text')
            ->filter()
            ->implode("\n\n---\n\n");

        $noteConsensusTexts = ConsensusNote::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNotNull('text')
            ->pluck('text')
            ->filter()
            ->implode("\n\n---\n\n");

        $consensusTexts = trim($responseConsensusTexts . ($noteConsensusTexts ? "\n\n---\n\n" . $noteConsensusTexts : ''));

        if (empty($consensusTexts)) {
            return response()->json([
                'success' => false,
                'message' => 'No consensus text available for extraction.',
            ], 422);
        }

        // Call LLM synchronously
        $llm = app(LlmService::class);
        $result = $llm->query(
            $llm->buildSynthesisPrompt(),
            $consensusTexts
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'LLM extraction failed: '.$result['error'],
                'provider' => $result['provider'],
            ], 500);
        }

        // Parse JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid LLM response format.',
                'provider' => $result['provider'],
            ], 500);
        }

        // Also store the extraction as a record (for background job compatibility)
        Extraction::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
                'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? '',
                'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? '',
                'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? '',
                'provider' => $result['provider'],
                'is_successful' => true,
                'extracted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? '',
            'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? '',
            'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? '',
            'provider' => $result['provider'],
        ]);
    }

    /**
     * Synchronous LLM synthesis for draft — returns parsed JSON directly.
     */
    public function synthesizeDraftSync(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'selected_group_ids' => 'nullable|array',
            'selected_group_ids.*' => 'exists:groups,id',
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
        ]);

        // Gather consensus notes from selected groups (or all if none selected)
        $groupIds = $validated['selected_group_ids'] ?? [];
        if (empty($groupIds)) {
            $groupIds = ConsensusNote::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->pluck('group_id')
                ->unique()
                ->toArray();
        }

        $notes = ConsensusNote::whereIn('group_id', $groupIds)
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->get();

        // Also include user's own draft fields as input
        $draft = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        $formattedNotes = $notes->map(function ($note) use ($draft) {
            $groupName = $note->group ? $note->group->name : 'Group ' . $note->group_id;
            $text = $note->text;
            // Append user's existing draft content for this group if available
            if ($draft) {
                $draftTexts = collect([
                    'Convergence: ' . ($draft->convergence_text ?? ''),
                    'Consideration: ' . ($draft->consideration_text ?? ''),
                    'Proposals: ' . ($draft->proposals_text ?? ''),
                ])->filter()->implode('\n');
                $text .= "\n\nUser's existing draft:\n" . $draftTexts;
            }
            return sprintf("=== Note for %s ===\n%s", $groupName, $text);
        })->implode("\n\n---\n\n");

        if (empty($formattedNotes) && empty(trim($validated['convergence_text'] ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'No content available for synthesis.',
            ], 422);
        }

        // Call LLM synchronously
        $llm = app(LlmService::class);
        $result = $llm->query(
            $llm->buildNotesSynthesisPrompt(),
            $formattedNotes ?: ($validated['convergence_text'] ?? '')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'LLM synthesis failed: '.$result['error'],
                'provider' => $result['provider'],
            ], 500);
        }

        // Parse JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid LLM response format.',
                'provider' => $result['provider'],
            ], 500);
        }

        // Update the draft in the database
        SynthesisDraft::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
                'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? $validated['convergence_text'] ?? '',
                'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? $validated['consideration_text'] ?? '',
                'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? $validated['proposals_text'] ?? '',
                'other_text' => $content['others'] ?? $content['other_text'] ?? ($draft?->other_text ?? ''),
            ]
        );

        return response()->json([
            'success' => true,
            'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? '',
            'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? '',
            'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? '',
            'other_text' => $content['others'] ?? $content['other_text'] ?? ($draft?->other_text ?? ''),
            'provider' => $result['provider'],
        ]);
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

        // Individual draft submissions are stored in SynthesisDraft only - they are separate from MasterSynthesis.
        // No need to create/update MasterSynthesis here.

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
     * Synchronous LLM synthesis summary — returns parsed JSON directly for immediate display.
     */
    public function summarySync(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $validated = $request->validate([
            'master_synthesis_id' => 'required|integer',
            'category' => 'required|in:convergence,consideration,proposals,others',
        ]);

        // Find all submitted SynthesisDraft records for this conversation
        $drafts = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No submitted drafts available for summary.',
            ], 422);
        }

        // Compile the draft content for LLM summarization
        $draftContent = $drafts->map(function ($draft) {
            return sprintf(
                "=== Draft by User %d ===\nConvergence: %s\n\nMatters for Consideration: %s\n\nProposals: %s\n\nOthers: %s\n",
                $draft->user_id,
                $draft->convergence_text ?? '(none)',
                $draft->consideration_text ?? '(none)',
                $draft->proposals_text ?? '(none)',
                $draft->other_text ?? '(none)'
            );
        })->implode("\n\n---\n\n");

        // Build the system prompt for summary generation
        $systemPrompt = match ($validated['category']) {
            'convergence' => <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a concise summary of the points of convergence/agreement across all submitted drafts.

Return your response as a JSON object with exactly this key:
{
  "convergence_summary": "Summary of convergences/agreements across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants. Highlight areas of consensus.
PROMPT,
            'consideration' => <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a concise summary of the matters for consideration/concerns across all submitted drafts.

Return your response as a JSON object with exactly this key:
{
  "consideration_summary": "Summary of matters for consideration/concerns across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants. Highlight areas of concern while acknowledging agreements.
PROMPT,
            'proposals' => <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a concise summary of the proposals/recommendations across all submitted drafts.

Return your response as a JSON object with exactly this key:
{
  "proposals_summary": "Summary of proposals/recommendations across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants. Group similar proposals together.
PROMPT,
            'others' => <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a concise summary of any other themes or topics across all submitted drafts that don't fit the main categories.

Return your response as a JSON object with exactly this key:
{
  "other_summary": "Summary of any other themes or topics across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants.
PROMPT,
        };

        // Call LLM synchronously
        $llm = app(LlmService::class);
        $result = $llm->query($systemPrompt, $draftContent);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'LLM summary failed: '.$result['error'],
                'provider' => $result['provider'],
            ], 500);
        }

        // Parse JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid LLM response format.',
                'provider' => $result['provider'],
            ], 500);
        }

        // Also store/update the summary in the database for background job compatibility
        $summaryField = $validated['category'] . '_summary';
        $existingSummary = SynthesisSummary::where('conversation_id', $conversation->id)->first();

        if ($existingSummary) {
            $existingSummary->update([
                $summaryField => $content[$summaryField] ?? null,
                'provider' => $result['provider'],
                'is_successful' => true,
                'generated_at' => now(),
            ]);
        } else {
            $summaryData = [
                'conversation_id' => $conversation->id,
                $summaryField => $content[$summaryField] ?? null,
                'provider' => $result['provider'],
                'is_successful' => true,
                'generated_at' => now(),
            ];
            SynthesisSummary::create($summaryData);
        }

        return response()->json([
            'success' => true,
            $summaryField => $content[$summaryField] ?? null,
            'provider' => $result['provider'],
        ]);
    }

    /**
     * Get the SynthesisSummary for a conversation.
     */
    public function getSynthesisSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        $synthesisSummary = SynthesisSummary::where('conversation_id', $conversation->id)->first();

        \Log::info('[DEBUG] getSynthesisSummary: fetched summary', [
            'conversation_id' => $conversation->id,
            'summary' => $synthesisSummary ? [
                'id' => $synthesisSummary->id,
                'convergence_summary' => $synthesisSummary->convergence_summary,
                'consideration_summary' => $synthesisSummary->consideration_summary,
                'proposals_summary' => $synthesisSummary->proposals_summary,
                'other_summary' => $synthesisSummary->other_summary,
                'status' => $synthesisSummary->status,
                'is_successful' => $synthesisSummary->is_successful,
            ] : null,
        ]);

        return response()->json([
            'summary' => $synthesisSummary ? [
                'id' => $synthesisSummary->id,
                'convergence_summary' => $synthesisSummary->convergence_summary,
                'consideration_summary' => $synthesisSummary->consideration_summary,
                'proposals_summary' => $synthesisSummary->proposals_summary,
                'other_summary' => $synthesisSummary->other_summary,
                'status' => $synthesisSummary->status,
                'is_successful' => $synthesisSummary->is_successful,
                'provider' => $synthesisSummary->provider,
                'generated_at' => $synthesisSummary->generated_at?->toIso8601String(),
            ] : null,
        ]);
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
