<?php

namespace App\Http\Controllers;

use App\Jobs\LlmExtractionJob;
use App\Jobs\LlmSynthesisSummaryJob;
use App\Models\ConsensusNote;
use App\Models\Conversation;
use App\Models\ConversationGroup;
use App\Models\Extraction;
use App\Models\Group;
use App\Models\MasterSynthesis;
use App\Models\Response;
use App\Models\SynthesisCustomQuery;
use App\Models\SynthesisDraft;
use App\Models\SynthesisKeyPoint;
use App\Models\SynthesisSummary;
use App\Models\User;
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

        // Get conversations filtered by assignment (or all if admin)
        $conversations = Conversation::whereHas('conversationGroups.synthesisMembers', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->get();

        if ($conversations->isEmpty() && $user->isAdmin()) {
            $conversations = Conversation::all();
        }
        
        // Determine which session is selected
        $selectedConversationId = $request->input('conversation_id');
        if (!$selectedConversationId && $conversations->isNotEmpty()) {
            $activeConv = $conversations->where('status', 'active')->first();
            $selectedConversationId = $activeConv ? $activeConv->id : $conversations->first()->id;
        }
        $selectedConversationId = (int) $selectedConversationId;

        // Get all groups for the selected conversation (not just assigned groups)
        $responses=Response::whereHas("conversationGroup", function($query) use ($user, $selectedConversationId){
            $query->where("conversation_id",$selectedConversationId)->whereHas('synthesisMembers', function ($q) use ($user){
                $q->where('users.id', $user->id);
            });
        })->with('conversationGroup.group')->get();
        
        $withoutResponse = Group::whereHas('conversationGroups', function ($query) use ($selectedConversationId, $user) {
            $query->where('conversation_id', $selectedConversationId)
                ->whereDoesntHave('responses')
                ->whereHas('synthesisMembers', function ($memberQuery) use ($user) {
                    $memberQuery->where('users.id', $user->id);
                });
        })->pluck('name')->toArray();
        $assignedGroups=$user->assignedConversationGroups()->where("conversation_id",$selectedConversationId)->get();

        $selectedConversation = $conversations->firstWhere('id', $selectedConversationId)
            ?? Conversation::find($selectedConversationId);

        $isSynthesizer = $selectedConversation
            ? $this->isSynthesizerForConversation($user, $selectedConversation)
            : false;

        return Inertia::render('synthesis/dashboard', [
            'conversations' => $conversations,
            'responses' => $responses,
            'selectedConversationId' => $selectedConversationId,
            'assignedGroups' => $assignedGroups,
            'isSynthesizer' => $isSynthesizer,
            'withoutResponse' => $withoutResponse,
        ]);
    }

    /**
     * Check if user is a synthesizer for at least one ConversationGroup in the given Conversation.
     */
    private function isSynthesizerForConversation(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->assignedConversationGroups()
            ->where('conversation_id', $conversation->id)
            ->wherePivot('is_synthesizer', true)
            ->exists();
    }

    /**
     * Authorize conversation access for non-admin users.
     */
    private function authorizeConversationAccess(User $user, Conversation $conversation): void
    {
        if ($user->isAdmin()) {
            return;
        }

        // Allow pastoral and discernment users to access (used by export-booklet route)
        if ($user->isPastoral() || $user->isDiscernment()) {
            return;
        }

        $isAssigned = $user->assignedConversationGroups()
            ->where('conversation_id', $conversation->id)
            ->exists();

        if (!$isAssigned) {
            abort(403, 'You are not assigned to this conversation.');
        }
    }

    /**
     * Show the review/edit page for a specific session's synthesis report.
     */
    public function review(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is assigned to this conversation or is admin
        $this->authorizeConversationAccess($user, $conversation);

        // Get assigned groups with their consensuses for this user & conversation
        $assignedConversationGroups = $user->assignedConversationGroups()
            ->where('conversation_id', $conversation->id)
            ->with(['group', 'responses'])
            ->get();

        // Fallback if no specific assigned groups, fetch all groups for this conversation
        if ($assignedConversationGroups->isEmpty()) {
            $assignedConversationGroups = ConversationGroup::where('conversation_id', $conversation->id)
                ->with(['group', 'responses'])
                ->get();
        }

        // Get user's notes for these responses
        $responseIds = $assignedConversationGroups->flatMap(fn($cg) => $cg->responses->pluck('id'));
        $notes = ConsensusNote::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereIn('response_id', $responseIds)
            ->get()
            ->keyBy('response_id');

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
        if ($assignedConversationGroups->isNotEmpty()) {
            $cgIds = $assignedConversationGroups->pluck('id');
            $responses = Response::whereIn('conversation_group_id', $cgIds)
                ->with('conversationGroup.group')
                ->get();

            $consensusSubmissions = $responses->groupBy('conversation_group_id')->map(function ($groupResponses) {
                return $groupResponses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'group_id' => $response->conversationGroup?->group_id,
                        'group_name' => $response->conversationGroup?->group?->name,
                        'submitted_at' => $response->submitted_at?->toIso8601String(),
                        'consensus_text' => $response->consensus_text,
                        'is_submitted' => !is_null($response->submitted_at),
                    ];
                })->values()->toArray();
            })->toArray();
        }

        return Inertia::render('synthesis/review', [
            'conversation' => $conversation,
            'assignedGroups' => $assignedConversationGroups->map(function ($cg) use ($consensusSubmissions, $notes) {
                $resp = $cg->responses->first();
                $note = $resp ? ($notes[$resp->id] ?? null) : null;
                return (object)[
                    'id' => $cg->id,
                    'group_id' => $cg->group_id,
                    'name' => $cg->group?->name,
                    'responses' => $cg->responses,
                    'consensusSubmission' => $consensusSubmissions[$cg->id][0] ?? null,
                    'note' => $note ? (object)[
                        'id' => $note->id,
                        'response_id' => $note->response_id,
                        'text' => $note->text,
                    ] : null,
                ];
            })->values()->toArray(),
            'notes' => $notes->map(fn($n) => (object)[
                'id' => $n->id,
                'response_id' => $n->response_id,
                'text' => $n->text,
            ])->values()->toArray(),
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
            'isSynthesizer' => $this->isSynthesizerForConversation($user, $conversation),
        ]);
    }



    /**
     * Handle a custom LLM query from the synthesis review page.
     */
    public function customQuery(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'selected_group_ids' => 'nullable|array',
            'selected_group_ids.*' => 'exists:groups,id',
        ]);

        return back()->with([
            'success' => 'Custom query feature requires LLM service integration.',
        ]);
    }

    /**
     * Delete a custom LLM query.
     */
    public function deleteCustomQuery(Request $request, SynthesisCustomQuery $query)
    {
        $user = $request->user();
        $report = $query->synthesisDraft;

        if (!$user->isAdmin() && $report->synthesis_id !== $user->id) {
            abort(403, 'You do not have access to this query.');
        }

        $query->delete();

        return back()->with('success', 'Custom query deleted.');
    }

    /**
     * Show the collaborative review page with all submissions grouped by category.
     */
    public function collaboratePage(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: only synthesis members assigned as synthesizer for this conversation (or admin) can access
        if (!$this->isSynthesizerForConversation($user, $conversation)) {
            abort(403, 'You are not assigned as a synthesizer for this conversation.');
        }

        // Fetch ALL conversation groups for this conversation
        $conversationGroups = ConversationGroup::where('conversation_id', $conversation->id)
            ->with(['group', 'responses'])
            ->get();

        // Build groups structure
        $groups = $conversationGroups->map(function ($cg) {
            return (object)[
                'id' => $cg->id,
                'group_id' => $cg->group_id,
                'name' => $cg->group?->name,
                'responses' => $cg->responses,
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

        // Fetch master synthesis for this conversation
        $masterSynthesis = $conversation->masterSynthesis;

        $masterSynthesisData = $masterSynthesis ? [
            'id' => $masterSynthesis->id,
            'convergence_text' => $masterSynthesis->convergence_text,
            'consideration_text' => $masterSynthesis->consideration_text,
            'proposals_text' => $masterSynthesis->proposals_text,
            'other_text' => $masterSynthesis->other_text,
            'status' => $masterSynthesis->status,
        ] : null;

        // Fetch consensus submission status from all conversation groups for this conversation
        $consensusSubmissions = [];
        if ($conversationGroups->isNotEmpty()) {
            $cgIds = $conversationGroups->pluck('id');
            $responses = Response::whereIn('conversation_group_id', $cgIds)
                ->with('conversationGroup.group')
                ->get();

            $consensusSubmissions = $responses->groupBy('conversation_group_id')->map(function ($groupResponses) {
                return $groupResponses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'group_id' => $response->conversationGroup?->group_id,
                        'group_name' => $response->conversationGroup?->group?->name,
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
            'submitted_drafts_count' => $synthesisSummary->submitted_drafts_count,
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
            'isSynthesizer' => true,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Export the current user's synthesis report as a booklet PDF.
     */
    public function exportBooklet(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        // Get the user's submitted synthesis report for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
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
            ->limit(1)
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

    /**
     * Get groups assigned to current user for a conversation.
     */
    public function getGroupAssignments(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $assignments = $user->assignedConversationGroups()
            ->where('conversation_id', $conversation->id)
            ->with('group')
            ->get();

        return response()->json([
            'assignments' => $assignments->map(fn($cg) => [
                'id' => $cg->id,
                'group_id' => $cg->group?->id,
                'group_name' => $cg->group?->name,
            ]),
        ]);
    }

    /**
     * Save/update notes for all groups in one batch request.
     * Accepts an array of { response_id, text } objects and upserts each.
     */
    public function saveBatchConsensusNotes(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.response_id' => 'required|exists:responses,id',
            'notes.*.text' => 'nullable|string',
        ]);

        foreach ($validated['notes'] as $item) {
            ConsensusNote::updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'response_id' => $item['response_id'],
                ],
                ['text' => $item['text'] ?? null]
            );
        }

        return response()->noContent();
    }

    /**
     * Manually trigger LLM extraction (queued job).
     */
    public function triggerExtraction(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

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
        $this->authorizeConversationAccess($user, $conversation);

        // Get assigned conversation group IDs
        $assignedConversationGroupIds = $user->assignedConversationGroups()
            ->where('conversation_id', $conversation->id)
            ->pluck('conversation_groups.id');

        if ($assignedConversationGroupIds->isEmpty()) {
            $assignedConversationGroupIds = Response::whereHas('conversationGroup', fn($q) =>
                $q->where('conversation_id', $conversation->id)
            )->whereNotNull('submitted_at')
                ->pluck('conversation_group_id')
                ->unique();
        }

        // Fetch consensus texts from submitted responses and consensus notes
        $responseConsensusTexts = Response::whereIn('conversation_group_id', $assignedConversationGroupIds)
            ->whereNotNull('submitted_at')
            ->pluck('consensus_text')
            ->filter()
            ->implode("\n\n---\n\n");

        $responseIds = Response::whereIn('conversation_group_id', $assignedConversationGroupIds)->pluck('id');

        $noteConsensusTexts = ConsensusNote::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereIn('response_id', $responseIds)
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

        // Also store the extraction as a record
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
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'selected_response_ids' => 'nullable|array',
            'selected_response_ids.*' => 'exists:responses,id',
            'selected_group_ids' => 'nullable|array',
            'selected_group_ids.*' => 'exists:groups,id',
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
        ]);

        $responseIds = $validated['selected_response_ids'] ?? [];
        if (empty($responseIds)) {
            $responseIds = ConsensusNote::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->pluck('response_id')
                ->unique()
                ->toArray();
        }

        $notes = ConsensusNote::whereIn('response_id', $responseIds)
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->with('response.conversationGroup.group')
            ->get();

        $draft = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        $formattedNotes = $notes->map(function ($note) use ($draft) {
            $groupName = $note->response?->conversationGroup?->group?->name ?? ('Response ' . $note->response_id);
            $text = $note->text;
            if ($draft) {
                $draftTexts = collect([
                    'Convergence: ' . ($draft->convergence_text ?? ''),
                    'Consideration: ' . ($draft->consideration_text ?? ''),
                    'Proposals: ' . ($draft->proposals_text ?? ''),
                ])->filter()->implode("\n");
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
     * Autosave draft content.
     */
    public function saveDraft(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

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

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        $draft = SynthesisDraft::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id]
        );

        $isSynthesizer = $this->isSynthesizerForConversation($user, $conversation);
        $redirectUrl = $isSynthesizer
            ? route('synthesis.collaborate', ['conversation' => $conversation])
            : route('synthesis.dashboard');

        if ($draft->is_submitted) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Synthesis draft already submitted.',
                    'redirect_url' => $redirectUrl,
                ]);
            }
            return redirect($redirectUrl)->with('info', 'Synthesis draft already submitted.');
        }

        // Update with text inputs if provided, and mark draft as submitted
        $updateData = array_merge(array_filter($validated, fn($val) => !is_null($val)), [
            'is_submitted' => true,
            'submitted_at' => now(),
        ]);

        $draft->update($updateData);

        // Auto-trigger summary check
        $this->checkAndAutoTriggerSummary($request, $conversation);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Synthesis submitted successfully.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        // Redirect to collaborative review page (for synthesizers) or dashboard
        return redirect($redirectUrl)->with('success', 'Synthesis submitted successfully.');
    }

    /**
     * Check if all assigned groups have submitted their consensus.
     */
    private function checkAllGroupsSubmitted(int $conversationId, int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $assignedConversationGroups = $user->assignedConversationGroups()
            ->where('conversation_id', $conversationId)
            ->get();

        if ($assignedConversationGroups->isEmpty()) {
            return false;
        }

        $submittedCount = Response::whereIn('conversation_group_id', $assignedConversationGroups->pluck('id'))
            ->whereNotNull('submitted_at')
            ->count();

        return $submittedCount >= $assignedConversationGroups->count();
    }

    // ─── Phase 2: Synthesis Flow Adjustments ─────────────────

    /**
     * Check if manual extraction trigger should be disabled.
     */
    public function canTriggerExtraction(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $hasSuccessfulExtraction = Extraction::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('is_successful', true)
            ->exists();

        return response()->json(['can_trigger' => !$hasSuccessfulExtraction]);
    }

    /**
     * Manually trigger synthesis summary generation for a master synthesis.
     */
    public function triggerSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $assignedUserIds = User::whereHas('assignedConversationGroups', fn($q) =>
            $q->where('conversation_id', $conversation->id)
        )->pluck('users.id');

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

        LlmSynthesisSummaryJob::dispatch($conversation->id);

        return response()->json(['success' => true, 'message' => 'Summary generation job dispatched.']);
    }

    /**
     * Synchronous LLM synthesis summary — generates summaries for all categories from submitted drafts.
     */
    public function summarySync(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $drafts = SynthesisDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No submitted drafts available for summary.',
            ], 422);
        }

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

        $systemPrompt = <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a comprehensive and concise summary for each of the following 4 categories across all submitted drafts:
1. Convergence: Points of agreement, common ground, and emerging unified conclusions.
2. Matters for Consideration: Concerns, dissenting opinions, unheard-of ideas, or objections.
3. Proposals: Actionable recommendations, practical suggestions, or required next steps.
4. Others: Any other themes or notable points not covered in the above 3 categories.

Return your response as a JSON object with exactly these keys:
{
  "convergence_summary": "Summary of convergences/agreements across all drafts",
  "consideration_summary": "Summary of matters for consideration/concerns across all drafts",
  "proposals_summary": "Summary of proposals/recommendations across all drafts",
  "other_summary": "Summary of other themes across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants.
PROMPT;

        $llm = app(LlmService::class);
        $result = $llm->query($systemPrompt, $draftContent);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'LLM summary failed: '.$result['error'],
                'provider' => $result['provider'],
            ], 500);
        }

        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid LLM response format.',
                'provider' => $result['provider'],
            ], 500);
        }

        $summaryData = [
            'convergence_summary' => $content['convergence_summary'] ?? null,
            'consideration_summary' => $content['consideration_summary'] ?? null,
            'proposals_summary' => $content['proposals_summary'] ?? null,
            'other_summary' => $content['other_summary'] ?? null,
            'submitted_drafts_count' => $drafts->count(),
            'status' => 'completed',
            'is_successful' => true,
            'provider' => $result['provider'],
            'generated_at' => now(),
        ];

        $summary = SynthesisSummary::updateOrCreate(
            ['conversation_id' => $conversation->id],
            $summaryData
        );

        return response()->json([
            'success' => true,
            'summary' => [
                'id' => $summary->id,
                'convergence_summary' => $summary->convergence_summary,
                'consideration_summary' => $summary->consideration_summary,
                'proposals_summary' => $summary->proposals_summary,
                'other_summary' => $summary->other_summary,
                'submitted_drafts_count' => $summary->submitted_drafts_count,
                'status' => $summary->status,
                'is_successful' => $summary->is_successful,
                'provider' => $summary->provider,
                'generated_at' => $summary->generated_at?->toIso8601String(),
            ],
            'provider' => $result['provider'],
        ]);
    }

    /**
     * Get the SynthesisSummary for a conversation.
     */
    public function getSynthesisSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $synthesisSummary = SynthesisSummary::where('conversation_id', $conversation->id)->first();

        return response()->json([
            'summary' => $synthesisSummary ? [
                'id' => $synthesisSummary->id,
                'convergence_summary' => $synthesisSummary->convergence_summary,
                'consideration_summary' => $synthesisSummary->consideration_summary,
                'proposals_summary' => $synthesisSummary->proposals_summary,
                'other_summary' => $synthesisSummary->other_summary,
                'submitted_drafts_count' => $synthesisSummary->submitted_drafts_count,
                'status' => $synthesisSummary->status,
                'is_successful' => $synthesisSummary->is_successful,
                'provider' => $synthesisSummary->provider,
                'generated_at' => $synthesisSummary->generated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Auto-trigger summary when all assigned members have submitted their drafts.
     */
    public function checkAndAutoTriggerSummary(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $assignedUserIds = User::whereHas('assignedConversationGroups', fn($q) =>
            $q->where('conversation_id', $conversation->id)
        )->pluck('users.id');

        if ($assignedUserIds->isEmpty()) {
            return response()->json(['triggered' => false, 'reason' => 'No assigned users']);
        }

        $submittedCount = SynthesisDraft::where('conversation_id', $conversation->id)
            ->whereIn('user_id', $assignedUserIds)
            ->where('is_submitted', true)
            ->count();

        if ($submittedCount < $assignedUserIds->count()) {
            return response()->json(['triggered' => false, 'reason' => 'Not all users have submitted']);
        }

        LlmSynthesisSummaryJob::dispatch($conversation->id);

        return response()->json(['triggered' => true]);
    }

    /**
     * Extract key points from master synthesis using LLM.
     */
    public function extractKeyPoints(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'max_key_points' => 'required|integer|min:1|max:10',
            'master_synthesis_id' => 'required|integer',
        ]);

        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->findOrFail($validated['master_synthesis_id']);

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

        $llmService = app(LlmService::class);
        $result = $llmService->extractKeyPoints($synthesisContent, $validated['max_key_points']);

        $keyPointsText = $result['content'];
        $keyPointsArray = array_filter(
            array_map('trim', explode("\n", $keyPointsText)),
            fn($line) => str_starts_with($line, '-')
        );

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

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

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

    /**
     * Update the key points text for a conversation.
     */
    public function updateKeyPoints(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization
        $this->authorizeConversationAccess($user, $conversation);

        $validated = $request->validate([
            'key_points_text' => 'required|string',
            'master_synthesis_id' => 'nullable|integer',
        ]);

        // Find the most recent completed key points for this conversation (prefer matching master_synthesis_id)
        $synthesisKeyPoint = SynthesisKeyPoint::where('conversation_id', $conversation->id)
            ->where('status', 'completed')
            ->when($validated['master_synthesis_id'], fn($q) => $q->where('master_synthesis_id', $validated['master_synthesis_id']))
            ->latest('updated_at')
            ->first();

        // If no existing record with matching master_synthesis_id, find any completed one
        if (!$synthesisKeyPoint) {
            $synthesisKeyPoint = SynthesisKeyPoint::where('conversation_id', $conversation->id)
                ->where('status', 'completed')
                ->latest('updated_at')
                ->first();
        }

        // If still no record, create one (should be rare)
        if (!$synthesisKeyPoint) {
            return response()->json([
                'success' => false,
                'message' => 'No key points record found. Please extract key points first.',
            ], 422);
        }

        $synthesisKeyPoint->update([
            'key_points_text' => $validated['key_points_text'],
            'key_points' => array_filter(
                array_map('trim', explode("\n", $validated['key_points_text'])),
                fn($line) => !empty($line)
            ),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Key points updated successfully.',
            'key_points' => [
                'id' => $synthesisKeyPoint->id,
                'key_points_text' => $synthesisKeyPoint->key_points_text,
                'key_points' => $synthesisKeyPoint->key_points ?? [],
            ],
        ]);
    }

    private function extractJsonFromLlmResponse(string $content): ?array
    {
        // Strip markdown code blocks if present
        $stripped = preg_replace('/^```(?:json)?\s*/', '', $content);
        $stripped = preg_replace('/\s*`$/', '', $stripped);

        // Try to decode as JSON
        $decoded = json_decode($stripped, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Try to find JSON object within the content
        $firstBrace = strpos($stripped, '{');
        $lastBrace = strrpos($stripped, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonString = substr($stripped, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonString, true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: try original content
        $decoded = json_decode($content, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }
}
