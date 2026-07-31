<?php

namespace App\Http\Controllers;

use App\Jobs\LlmDecisionSummaryJob;
use App\Models\Conversation;
use App\Models\DecisionDraft;
use App\Models\DecisionSummary;
use App\Models\Diocese;
use App\Models\DioceseDecisionDraft;
use App\Models\Event;
use App\Models\MasterDecision;
use App\Models\MasterDecisionSummary;
use App\Models\NationalDecisionDraft;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastoralController extends Controller
{
    /**
     * Authorize user has pastoral or discernment role.
     */
    private function authorizePastoralAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user->isPastoral() && !$user->isDiscernment() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access to pastoral section.');
        }
    }

    /**
     * Returns pastoral dashboard view.
     */
    public function index(Request $request)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        $conversations = Conversation::with(['event'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'status', 'description', 'event_id', 'created_at'])
            ->map(function ($conversation) use ($user) {
                // Check report availability (MasterSynthesis submitted)
                $hasMasterSynthesisSubmitted = $conversation->masterSyntheses()
                    ->where('is_master_synthesis', true)
                    ->where('is_submitted', true)
                    ->exists();

                // Get event-level decision submission status
                $eventLevelSubmitted = null;
                if ($conversation->event_id) {
                    $eventLevelSubmitted = NationalDecisionDraft::where('event_id', $conversation->event_id)
                        ->where('user_id', $user->id)
                        ->where('is_submitted', true)
                        ->exists();
                }

                // Get media files from storage/app/public/media/{conversation-title-lowercase}/ folder
                $mediaFiles = [];
                $folder = strtolower(str_replace(' ', '', $conversation->title));
                $storagePath = 'media/' . $folder;

                // Check both new storage location and old public location for backward compatibility
                $newPath = storage_path('app/public/' . $storagePath);
                $oldPath = public_path($folder);

                $files = [];
                if (is_dir($newPath)) {
                    $files = scandir($newPath);
                } elseif (is_dir($oldPath)) {
                    // Fallback to old location for backward compatibility
                    $files = scandir($oldPath);
                }

                if ($files) {
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && is_file(
                            is_dir($newPath) ? "{$newPath}/{$file}" : "{$oldPath}/{$file}"
                        )) {
                            $mediaFiles[] = [
                                'name' => $file,
                                'path' => '/storage/' . $storagePath . '/' . $file,
                                'size' => filesize(
                                    is_dir($newPath) ? "{$newPath}/{$file}" : "{$oldPath}/{$file}"
                                ),
                            ];
                        }
                    }
                }

                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'status' => $conversation->status,
                    'description' => $conversation->description,
                    'event_id' => $conversation->event_id,
                    'event_title' => $conversation->event?->title,
                    'has_reports' => $hasMasterSynthesisSubmitted,
                    'user_draft_submitted' => $eventLevelSubmitted ?? DecisionDraft::where('conversation_id', $conversation->id)
                        ->where('user_id', $user->id)
                        ->where('is_submitted', true)
                        ->exists(),
                    'media_files' => $mediaFiles,
                ];
            });

        return Inertia::render('pastoral/dashboard', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Returns reports view for a conversation.
     */
    public function reports(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $masterSynthesis = $conversation->masterSyntheses()
            ->where('is_master_synthesis', true)
            ->where('is_submitted', true)
            ->first();

        $hasReports = $masterSynthesis !== null;

        return Inertia::render('pastoral/reports', [
            'conversation' => $conversation,
            'hasReports' => $hasReports,
            'reports' => $hasReports ? [
                'booklet' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
                'infographic' => route('synthesis.export-booklet', ['conversation' => $conversation->id]), // link to standard export
                'slides' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
                'tables' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
            ] : null,
        ]);
    }

    /**
     * Shows decision overview page for a conversation (renders decisions-view).
     */
    public function decisionDraft(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        // Master decisions for the conversation
        $masterDecisions = MasterDecision::where('conversation_id', $conversation->id)
            ->get();

        // Decision summaries for the conversation
        $decisionSummaries = DecisionSummary::where('conversation_id', $conversation->id)
            ->get();

        // Decision drafts for the conversation
        $decisionDrafts = DecisionDraft::where('conversation_id', $conversation->id)
            ->with(['user', 'diocese'])
            ->get();

        // Dioceses list
        $dioceses = Diocese::all();

        return Inertia::render('pastoral/decisions-view', [
            'masterDecisions' => $masterDecisions,
            'decisionSummaries' => $decisionSummaries,
            'decisionDrafts' => $decisionDrafts,
            'dioceses' => $dioceses,
            'userRole' => $user->role,
        ]);
    }

    /**
     * Store decision draft for a conversation.
     */
    public function storeDecisionDraft(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        $validated = $request->validate([
            'national_text' => 'nullable|string',
            'diocesan_text' => 'nullable|string',
            'is_submitted' => 'nullable|boolean',
        ]);

        $isSubmitted = $validated['is_submitted'] ?? false;

        $draft = DecisionDraft::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'diocese_id' => $user->diocese_id,
                'national_text' => $validated['national_text'] ?? null,
                'diocesan_text' => $validated['diocesan_text'] ?? null,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]
        );

        // Auto-check if all pastoral members submitted for national/diocese levels to trigger summary
        $this->checkAutoTriggerDecisionSummaries($conversation, $user);

        return back()->with('success', 'Decision draft saved successfully.');
    }

    /**
     * View collaborative decisions page.
     */
    public function decisions(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        // Master decisions for the conversation
        $masterDecisions = MasterDecision::where('conversation_id', $conversation->id)
            ->get();

        // Decision summaries for the conversation
        $decisionSummaries = DecisionSummary::where('conversation_id', $conversation->id)
            ->get();

        // Decision drafts for the conversation
        $decisionDrafts = DecisionDraft::where('conversation_id', $conversation->id)
            ->with(['user', 'diocese'])
            ->get();

        // Dioceses list
        $dioceses = Diocese::all();

        return Inertia::render('pastoral/decisions-view', [
            'masterDecisions' => $masterDecisions,
            'decisionSummaries' => $decisionSummaries,
            'decisionDrafts' => $decisionDrafts,
            'dioceses' => $dioceses,
            'userRole' => $user->role,
        ]);
    }

    /**
     * Manually trigger decision summary for national or diocese level.
     */
    public function decisionSummary(Request $request, Conversation $conversation, string $level)
    {
        $this->authorizePastoralAccess($request);

        if (!in_array($level, ['national', 'diocese'])) {
            return back()->withErrors(['level' => 'Invalid summary level.']);
        }

        $user = $request->user();
        $dioceseId = ($level === 'diocese') ? $user->diocese_id : null;

        // Check backend lock: if all submitted AND successful summary exists, reject
        $existingSuccessful = DecisionSummary::where('conversation_id', $conversation->id)
            ->where('level', $level)
            ->where('diocese_id', $dioceseId)
            ->where('is_successful', true)
            ->exists();

        if ($existingSuccessful) {
            return back()->withErrors(['summary' => 'A successful summary already exists and manual trigger is disabled.']);
        }

        LlmDecisionSummaryJob::dispatch($conversation->id, $dioceseId, $level);

        return back()->with('success', "Decision summary generation for {$level} level dispatched.");
    }

    /**
     * Update MasterDecision for national or diocese level.
     */
    public function updateMasterDecision(Request $request, Conversation $conversation, string $level)
    {
        $this->authorizePastoralAccess($request);

        if (!in_array($level, ['national', 'diocese'])) {
            return back()->withErrors(['level' => 'Invalid decision level.']);
        }

        $user = $request->user();
        $dioceseId = ($level === 'diocese') ? $user->diocese_id : null;

        // Authorization check: if diocese level, pastoral member can only edit their own diocese master decision
        if ($level === 'diocese' && !$user->isAdmin()) {
            $reqDioceseId = $request->input('diocese_id', $user->diocese_id);
            if ((int)$reqDioceseId !== (int)$user->diocese_id) {
                abort(403, 'You can only edit your own diocese master decision.');
            }
            $dioceseId = (int)$reqDioceseId;
        }

        $validated = $request->validate([
            'decision_text' => 'required|string',
            'is_submitted' => 'nullable|boolean',
            'diocese_id' => 'nullable|integer',
        ]);

        if (isset($validated['diocese_id']) && $level === 'diocese') {
            $dioceseId = $validated['diocese_id'];
        }

        $masterDecision = MasterDecision::where('conversation_id', $conversation->id)
            ->where('level', $level)
            ->where('diocese_id', $dioceseId)
            ->first();

        if ($masterDecision && $masterDecision->is_submitted) {
            return back()->withErrors(['master_decision' => 'This master decision is locked and cannot be edited.']);
        }

        $isSubmitted = $validated['is_submitted'] ?? false;

        MasterDecision::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'level' => $level,
                'diocese_id' => $dioceseId,
            ],
            [
                'decision_text' => $validated['decision_text'],
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]
        );

        return back()->with('success', 'Master decision updated successfully.');
    }

    /**
     * Helper to auto-trigger decision summary when all required members have submitted.
     */
    private function checkAutoTriggerDecisionSummaries(Conversation $conversation, $user): void
    {
        // 1. National level check
        $totalPastoralUsers = \App\Models\User::whereIn('role', ['pastoral', 'discernment'])->count();
        $submittedNationalCount = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->whereNotNull('national_text')
            ->count();

        if ($totalPastoralUsers > 0 && $submittedNationalCount >= $totalPastoralUsers) {
            $hasNationalSummary = DecisionSummary::where('conversation_id', $conversation->id)
                ->where('level', 'national')
                ->where('is_successful', true)
                ->exists();

            if (!$hasNationalSummary) {
                LlmDecisionSummaryJob::dispatch($conversation->id, null, 'national');
            }
        }

        // 2. Diocese level check
        if ($user->diocese_id) {
            $totalDioceseUsers = \App\Models\User::where('diocese_id', $user->diocese_id)
                ->whereIn('role', ['pastoral', 'discernment'])
                ->count();

            $submittedDioceseCount = DecisionDraft::where('conversation_id', $conversation->id)
                ->where('diocese_id', $user->diocese_id)
                ->where('is_submitted', true)
                ->whereNotNull('diocesan_text')
                ->count();

            if ($totalDioceseUsers > 0 && $submittedDioceseCount >= $totalDioceseUsers) {
                $hasDioceseSummary = DecisionSummary::where('conversation_id', $conversation->id)
                    ->where('level', 'diocese')
                    ->where('diocese_id', $user->diocese_id)
                    ->where('is_successful', true)
                    ->exists();

                if (!$hasDioceseSummary) {
                    LlmDecisionSummaryJob::dispatch($conversation->id, $user->diocese_id, 'diocese');
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Event-Level Decision Making Methods
    // ---------------------------------------------------------------------

    /**
     * GET page for Begin Decision Making.
     * Fetches existing drafts for the current user and event.
     */
    public function decision(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        $nationalDraft = NationalDecisionDraft::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        $dioceseDraft = DioceseDecisionDraft::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        $userSubmitted = (bool) $nationalDraft?->is_submitted || (bool) $dioceseDraft?->is_submitted;

        return Inertia::render('pastoral/decision', [
            'event' => $event,
            'nationalDraft' => $nationalDraft ? [
                'id' => $nationalDraft->id,
                'draft_text' => $nationalDraft->draft_text,
                'is_submitted' => $nationalDraft->is_submitted,
                'submitted_at' => $nationalDraft->submitted_at?->toIso8601String(),
            ] : null,
            'dioceseDraft' => $dioceseDraft ? [
                'id' => $dioceseDraft->id,
                'draft_text' => $dioceseDraft->draft_text,
                'is_submitted' => $dioceseDraft->is_submitted,
                'submitted_at' => $dioceseDraft->submitted_at?->toIso8601String(),
            ] : null,
            'userSubmitted' => $userSubmitted,
        ]);
    }

    /**
     * POST store decision drafts.
     * Creates or updates NationalDecisionDraft and DioceseDecisionDraft for current user + event.
     */
    public function storeDecisionDrafts(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();

        $validated = $request->validate([
            'national_draft_text' => 'nullable|string',
            'diocese_draft_text' => 'nullable|string',
            'submit' => 'boolean',
        ]);

        $isSubmitted = $validated['submit'] ?? false;

        // Update or create NationalDecisionDraft
        NationalDecisionDraft::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'diocese_id' => $user->diocese_id,
                'draft_text' => $validated['national_draft_text'] ?? null,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]
        );

        // Update or create DioceseDecisionDraft
        DioceseDecisionDraft::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
            ],
            [
                'diocese_id' => $user->diocese_id,
                'draft_text' => $validated['diocese_draft_text'] ?? null,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $isSubmitted ? now() : null,
            ]
        );

        return back()->with('success', 'Decision drafts saved successfully.');
    }

    /**
     * GET page for Collaborative Decision.
     * Fetches all submitted NationalDecisionDrafts, DioceseDecisionDrafts and MasterDecisionSummary for the event.
     */
    public function collaborate(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);

        // Fetch all submitted NationalDecisionDrafts with user and diocese relationships
        $nationalDrafts = NationalDecisionDraft::where('event_id', $event->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get()
            ->map(function ($draft) {
                return [
                    'id' => $draft->id,
                    'user_id' => $draft->user_id,
                    'draft_text' => $draft->draft_text,
                    'is_submitted' => $draft->is_submitted,
                    'submitted_at' => $draft->submitted_at?->toIso8601String(),
                    'created_at' => $draft->created_at?->toIso8601String(),
                    'user' => $draft->user ? [
                        'id' => $draft->user->id,
                        'name' => $draft->user->name,
                        'email' => $draft->user->email,
                    ] : null,
                    'diocese' => $draft->diocese ? [
                        'id' => $draft->diocese->id,
                        'name' => $draft->diocese->name,
                        'code' => $draft->diocese->code,
                    ] : null,
                ];
            });

        // Fetch all submitted DioceseDecisionDrafts with user and diocese relationships
        $dioceseDrafts = DioceseDecisionDraft::where('event_id', $event->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get()
            ->map(function ($draft) {
                return [
                    'id' => $draft->id,
                    'user_id' => $draft->user_id,
                    'draft_text' => $draft->draft_text,
                    'is_submitted' => $draft->is_submitted,
                    'submitted_at' => $draft->submitted_at?->toIso8601String(),
                    'created_at' => $draft->created_at?->toIso8601String(),
                    'user' => $draft->user ? [
                        'id' => $draft->user->id,
                        'name' => $draft->user->name,
                        'email' => $draft->user->email,
                    ] : null,
                    'diocese' => $draft->diocese ? [
                        'id' => $draft->diocese->id,
                        'name' => $draft->diocese->name,
                        'code' => $draft->diocese->code,
                    ] : null,
                ];
            });

        // Fetch MasterDecisionSummary for the event
        $masterDecisionSummary = MasterDecisionSummary::where('event_id', $event->id)->first();

        return Inertia::render('pastoral/collaborate', [
            'event' => $event,
            'nationalDrafts' => $nationalDrafts,
            'masterDecisionSummary' => $masterDecisionSummary ? [
                'id' => $masterDecisionSummary->id,
                'master_text' => $masterDecisionSummary->master_text,
                'is_editable' => $masterDecisionSummary->is_editable,
                'generated_by' => $masterDecisionSummary->generated_by,
                'generated_at' => $masterDecisionSummary->generated_at?->toIso8601String(),
            ] : null,
            'dioceseDrafts' => $dioceseDrafts,
        ]);
    }

    /**
     * POST generate summary via LLM.
     * Fetches all submitted NationalDecisionDrafts and uses LlmService to generate a cohesive summary.
     */
    public function generateDecisionSummary(Request $request, Event $event, LlmService $llmService): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        // Fetch all submitted NationalDecisionDrafts for the event
        $drafts = NationalDecisionDraft::where('event_id', $event->id)
            ->where('is_submitted', true)
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No submitted decision drafts available for summary generation.',
            ], 422);
        }

        // Gather all draft_text values
        $draftTexts = $drafts->map(fn($draft) => "[User: {$draft->user->name}" . ($draft->diocese ? ", Diocese: {$draft->diocese->name}" : '') . "]\n{$draft->draft_text}")->join("\n\n---\n\n");

        $systemPrompt = 'You are a synthesis assistant for a consultative process. Your task is to consolidate multiple decision drafts from pastoral members into a single cohesive master summary. Preserve the key points, agreements, and proposals from all contributors while eliminating redundancies. Output should be well-structured markdown text.';

        $userPrompt = "Please generate a consolidated master decision summary from the following submitted drafts:\n\n{$draftTexts}";

        $result = $llmService->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate summary: ' . $result['error'],
            ], 500);
        }

        // Create or update MasterDecisionSummary with the result
        $user = $request->user();
        $masterSummary = MasterDecisionSummary::updateOrCreate(
            [
                'event_id' => $event->id,
            ],
            [
                'master_text' => $result['content'],
                'is_editable' => true,
                'generated_by' => $result['provider'],
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'master_text' => $result['content'],
            'generated_by' => $result['provider'],
            'master_summary_id' => $masterSummary->id,
        ]);
    }

    /**
     * PATCH save master text.
     * Updates the MasterDecisionSummary for the event.
     */
    public function saveMasterText(Request $request, Event $event): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'master_text' => 'required|string',
        ]);

        $masterSummary = MasterDecisionSummary::where('event_id', $event->id)->first();

        if (!$masterSummary) {
            return response()->json([
                'success' => false,
                'error' => 'No master summary exists for this event.',
            ], 404);
        }

        $masterSummary->update([
            'master_text' => $validated['master_text'],
            'is_editable' => true,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * GET page for Bishop's Overview.
     * Fetches MasterDecisionSummary for the event.
     */
    public function bishopOverview(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);

        $masterDecisionSummary = MasterDecisionSummary::where('event_id', $event->id)->first();

        return Inertia::render('pastoral/bishop-overview', [
            'event' => $event,
            'masterDecisionSummary' => $masterDecisionSummary ? [
                'id' => $masterDecisionSummary->id,
                'master_text' => $masterDecisionSummary->master_text,
                'is_editable' => $masterDecisionSummary->is_editable,
                'generated_by' => $masterDecisionSummary->generated_by,
                'generated_at' => $masterDecisionSummary->generated_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
