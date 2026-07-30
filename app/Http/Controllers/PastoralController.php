<?php

namespace App\Http\Controllers;

use App\Jobs\LlmDecisionSummaryJob;
use App\Models\Conversation;
use App\Models\DecisionDraft;
use App\Models\DecisionSummary;
use App\Models\Diocese;
use App\Models\MasterDecision;
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

                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'status' => $conversation->status,
                    'description' => $conversation->description,
                    'event_title' => $conversation->event?->title,
                    'has_reports' => $hasMasterSynthesisSubmitted,
                    'user_draft_submitted' => DecisionDraft::where('conversation_id', $conversation->id)
                        ->where('user_id', $user->id)
                        ->where('is_submitted', true)
                        ->exists(),
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

        // National level submissions
        $allNationalSubmissions = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with('user')
            ->get(['id', 'user_id', 'national_text', 'submitted_at']);

        // National summary
        $nationalSummary = DecisionSummary::where('conversation_id', $conversation->id)
            ->where('level', 'national')
            ->first();

        // National master decision
        $nationalMasterDecision = MasterDecision::where('conversation_id', $conversation->id)
            ->where('level', 'national')
            ->first();

        // Dioceses list
        $dioceses = Diocese::all();

        // Diocese level submissions grouped by diocese
        $dioceseSubmissions = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->whereNotNull('diocese_id')
            ->with(['user', 'diocese'])
            ->get()
            ->groupBy('diocese_id');

        // Diocese summaries keyed by diocese_id
        $dioceseSummaries = DecisionSummary::where('conversation_id', $conversation->id)
            ->where('level', 'diocese')
            ->get()
            ->keyBy('diocese_id');

        // Diocese master decisions keyed by diocese_id
        $dioceseMasterDecisions = MasterDecision::where('conversation_id', $conversation->id)
            ->where('level', 'diocese')
            ->get()
            ->keyBy('diocese_id');

        return Inertia::render('pastoral/decisions', [
            'conversation' => $conversation,
            'allNationalSubmissions' => $allNationalSubmissions,
            'nationalSummary' => $nationalSummary,
            'nationalMasterDecision' => $nationalMasterDecision,
            'dioceses' => $dioceses,
            'dioceseSubmissions' => $dioceseSubmissions,
            'dioceseSummaries' => $dioceseSummaries,
            'dioceseMasterDecisions' => $dioceseMasterDecisions,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'diocese_id' => $user->diocese_id,
            ],
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
}
