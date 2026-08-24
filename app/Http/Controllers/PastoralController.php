<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\DecisionDraft;
use App\Models\DecisionPart;
use App\Models\Diocese;
use App\Models\Event;
use App\Models\MasterDecision;
use App\Models\User;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastoralController extends Controller
{
    /**
     * Authorize user has pastoral, discernment, or admin role.
     */
    private function authorizePastoralAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user->isPastoral() && !$user->isDiscernment() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access to pastoral section.');
        }
    }

    /**
     * Get total pastoral and discernment members count.
     */
    private function getTotalPastoralMembersCount(): int
    {
        return User::whereIn('role', ['pastoral', 'discernment'])->count();
    }

    /**
     * Check if all pastoral members have submitted their DecisionDraft for a conversation.
     */
    private function areAllDraftsSubmittedForConversation(Conversation $conversation): bool
    {
        $totalMembers = $this->getTotalPastoralMembersCount();
        if ($totalMembers === 0) {
            return false;
        }

        $submittedCount = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->count();

        return $submittedCount >= $totalMembers;
    }

    /**
     * Check if all conversations for an event have their DecisionPart submitted.
     */
    private function areAllDecisionPartsSubmittedForEvent(Event $event): bool
    {
        $conversationIds = $event->conversations()->pluck('id');
        if ($conversationIds->isEmpty()) {
            return false;
        }

        $submittedPartsCount = DecisionPart::whereIn('conversation_id', $conversationIds)
            ->where('is_submitted', true)
            ->count();

        return $submittedPartsCount === $conversationIds->count();
    }

    /**
     * Returns pastoral dashboard view with per-conversation and event progress.
     */
    public function index(Request $request): Response
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();
        $totalPastoralCount = $this->getTotalPastoralMembersCount();

        $conversations = Conversation::with(['event', 'masterSynthesis', 'decisionPart'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($user, $totalPastoralCount) {
                $masterSynthesis = $conversation->masterSynthesis;

                $userDraft = DecisionDraft::where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                $submittedDraftsCount = DecisionDraft::where('conversation_id', $conversation->id)
                    ->where('is_submitted', true)
                    ->count();

                $decisionPart = $conversation->decisionPart;

                // Check media files
                $mediaFiles = [];
                $folder = strtolower(str_replace(' ', '', $conversation->title));
                $storagePath = 'media/' . $folder;
                $newPath = storage_path('app/public/' . $storagePath);
                $oldPath = public_path($folder);

                $files = [];
                if (is_dir($newPath)) {
                    $files = scandir($newPath);
                } elseif (is_dir($oldPath)) {
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
                    'has_reports' => (bool) $masterSynthesis?->is_submitted,
                    
                    // Stage 1: Individual Draft
                    'user_draft_submitted' => (bool) $userDraft?->is_submitted,
                    'submitted_drafts_count' => $submittedDraftsCount,
                    'total_pastoral_count' => $totalPastoralCount,
                    'all_drafts_submitted' => $totalPastoralCount > 0 && $submittedDraftsCount >= $totalPastoralCount,
                    
                    // Stage 2: Shared DecisionPart
                    'decision_part_exists' => (bool) $decisionPart,
                    'decision_part_submitted' => (bool) $decisionPart?->is_submitted,

                    'media_files' => $mediaFiles,
                ];
            });

        $events = Event::with('conversations')->get()->map(function ($event) {
            $allPartsSubmitted = $this->areAllDecisionPartsSubmittedForEvent($event);
            $masterDecision = MasterDecision::where('event_id', $event->id)->first();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'conversations_count' => $event->conversations->count(),
                'all_decision_parts_submitted' => $allPartsSubmitted,
                'master_decision_submitted' => (bool) $masterDecision?->is_submitted,
            ];
        });

        return Inertia::render('pastoral/dashboard', [
            'conversations' => $conversations,
            'events' => $events,
        ]);
    }

    /**
     * Returns reports view for a conversation.
     */
    public function reports(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);

        $masterSynthesis = $conversation->masterSynthesis;
        $hasReports = $masterSynthesis && $masterSynthesis->is_submitted;

        return Inertia::render('pastoral/reports', [
            'conversation' => $conversation,
            'hasReports' => $hasReports,
            'reports' => $hasReports ? [
                'booklet' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
                'infographic' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
                'slides' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
                'tables' => route('synthesis.export-booklet', ['conversation' => $conversation->id]),
            ] : null,
        ]);
    }

    /**
     * GET Per-Conversation Decision Draft (Member Scope).
     */
    public function conversationDecision(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $draft = DecisionDraft::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'diocese_id' => $user->diocese_id,
                'is_submitted' => false,
            ]
        );

        $allMembers = User::whereIn('role', ['pastoral', 'discernment'])
            ->with('diocese')
            ->get();

        $submittedMemberIds = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->pluck('user_id')
            ->toArray();

        $eventConversations = $conversation->event
            ? $conversation->event->conversations()->select('id', 'title', 'event_id')->get()
            : collect([$conversation]);

        return Inertia::render('pastoral/decision', [
            'conversation' => $conversation->load('event'),
            'eventConversations' => $eventConversations,
            'draft' => [
                'id' => $draft->id,
                'national_text' => $draft->national_text,
                'diocesan_text' => $draft->diocesan_text,
                'is_submitted' => $draft->is_submitted,
                'submitted_at' => $draft->submitted_at?->toIso8601String(),
            ],
            'submissionProgress' => [
                'total' => $allMembers->count(),
                'submitted_count' => count($submittedMemberIds),
                'members' => $allMembers->map(fn($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'diocese' => $m->diocese?->name,
                    'is_submitted' => in_array($m->id, $submittedMemberIds),
                ]),
            ],
        ]);
    }

    /**
     * POST Store/Submit Per-Conversation Decision Draft.
     */
    public function storeConversationDraft(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'national_text' => 'nullable|string',
            'diocesan_text' => 'nullable|string',
            'is_submitted' => 'required|boolean',
        ]);

        DecisionDraft::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'diocese_id' => $user->diocese_id,
                'national_text' => $validated['national_text'] ?? null,
                'diocesan_text' => $validated['diocesan_text'] ?? null,
                'is_submitted' => $validated['is_submitted'],
                'submitted_at' => $validated['is_submitted'] ? now() : null,
            ]
        );

        return back()->with('success', $validated['is_submitted']
            ? 'Decision draft submitted successfully.'
            : 'Draft saved.');
    }

    /**
     * GET Collaborate on Shared DecisionPart for a Conversation.
     */
    public function collaborateConversation(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);

        $isUnlocked = $this->areAllDraftsSubmittedForConversation($conversation);
        $totalMembers = $this->getTotalPastoralMembersCount();
        $submittedDraftsCount = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->count();

        // Fetch all submitted drafts for this conversation with user & diocese relations
        $memberDrafts = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get()
            ->map(function ($draft) {
                return [
                    'id' => $draft->id,
                    'user_id' => $draft->user_id,
                    'national_text' => $draft->national_text,
                    'diocesan_text' => $draft->diocesan_text,
                    'is_submitted' => $draft->is_submitted,
                    'submitted_at' => $draft->submitted_at?->toIso8601String(),
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

        // Fetch shared DecisionPart
        $decisionPart = DecisionPart::where('conversation_id', $conversation->id)
            ->with('submittedBy')
            ->first();

        // Conversations in the same event for navigation
        $eventConversations = $conversation->event
            ? $conversation->event->conversations()->select('id', 'title', 'event_id')->get()
            : collect([$conversation]);

        return Inertia::render('pastoral/collaborate', [
            'conversation' => $conversation->load('event'),
            'eventConversations' => $eventConversations,
            'isUnlocked' => $isUnlocked,
            'progress' => [
                'submitted_count' => $submittedDraftsCount,
                'total_required' => $totalMembers,
            ],
            'memberDrafts' => $memberDrafts,
            'decisionPart' => $decisionPart ? [
                'id' => $decisionPart->id,
                'national_text' => $decisionPart->national_text,
                'diocesan_text' => $decisionPart->diocesan_text,
                'consolidated_text' => $decisionPart->consolidated_text,
                'is_submitted' => $decisionPart->is_submitted,
                'submitted_at' => $decisionPart->submitted_at?->toIso8601String(),
                'submitted_by' => $decisionPart->submittedBy ? [
                    'id' => $decisionPart->submittedBy->id,
                    'name' => $decisionPart->submittedBy->name,
                ] : null,
                'generated_by' => $decisionPart->generated_by,
                'generated_at' => $decisionPart->generated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST Generate DecisionPart Summary with LLM.
     */
    public function generateDecisionPartSummary(Request $request, Conversation $conversation, LlmService $llmService): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        if (!$this->areAllDraftsSubmittedForConversation($conversation)) {
            return response()->json([
                'success' => false,
                'error' => 'All pastoral members must submit their drafts before generating a summary.',
            ], 403);
        }

        $drafts = DecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get();

        if ($drafts->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'No submitted drafts found.'], 422);
        }

        $draftTexts = $drafts->map(function ($d) {
            $meta = "[Member: {$d->user->name}" . ($d->diocese ? ", Diocese: {$d->diocese->name}" : '') . "]";
            return "{$meta}\nNational Reflection:\n{$d->national_text}\n\nDiocesan Reflection:\n{$d->diocesan_text}";
        })->join("\n\n---\n\n");

        $systemPrompt = "You are a pastoral synthesis assistant for a consultative synodal process. Synthesize the submitted individual decision drafts for session '{$conversation->title}' into a cohesive, structured decision part. Preserve critical consensus points, key diocesan concerns, and concrete recommendations. Format your output in clean Markdown.";
        $userPrompt = "Please synthesize the following individual pastoral drafts into a consolidated decision part:\n\n{$draftTexts}";

        $result = $llmService->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => 'LLM generation failed: ' . $result['error']], 500);
        }

        $decisionPart = DecisionPart::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'consolidated_text' => $result['content'],
                'generated_by' => $result['provider'],
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'consolidated_text' => $result['content'],
            'generated_by' => $result['provider'],
            'decision_part_id' => $decisionPart->id,
        ]);
    }

    /**
     * PATCH Save shared DecisionPart draft.
     */
    public function saveDecisionPart(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'consolidated_text' => 'required|string',
            'national_text' => 'nullable|string',
            'diocesan_text' => 'nullable|string',
        ]);

        $part = DecisionPart::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'consolidated_text' => $validated['consolidated_text'],
                'national_text' => $validated['national_text'] ?? null,
                'diocesan_text' => $validated['diocesan_text'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'part' => $part]);
    }

    /**
     * POST Finalize shared DecisionPart.
     */
    public function finalizeDecisionPart(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'consolidated_text' => 'required|string',
        ]);

        DecisionPart::updateOrCreate(
            ['conversation_id' => $conversation->id],
            [
                'consolidated_text' => $validated['consolidated_text'],
                'is_submitted' => true,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->id,
            ]
        );

        return back()->with('success', "Decision Part for '{$conversation->title}' has been finalized.");
    }

    /**
     * GET Event-Level Master Decision Review Page.
     */
    public function masterDecision(Request $request, Event $event): Response
    {
        $this->authorizePastoralAccess($request);

        $isUnlocked = $this->areAllDecisionPartsSubmittedForEvent($event);

        // Fetch all conversations with their DecisionPart
        $conversationsWithParts = $event->conversations()
            ->with(['decisionPart' => function ($query) {
                $query->with('submittedBy');
            }])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'status' => $c->status,
                    'decision_part' => $c->decisionPart ? [
                        'id' => $c->decisionPart->id,
                        'consolidated_text' => $c->decisionPart->consolidated_text,
                        'is_submitted' => $c->decisionPart->is_submitted,
                        'submitted_at' => $c->decisionPart->submitted_at?->toIso8601String(),
                        'submitted_by' => $c->decisionPart->submittedBy?->name,
                    ] : null,
                ];
            });

        $masterDecision = MasterDecision::where('event_id', $event->id)
            ->with('submittedBy')
            ->first();

        return Inertia::render('pastoral/master-decision', [
            'event' => $event,
            'isUnlocked' => $isUnlocked,
            'conversationsWithParts' => $conversationsWithParts,
            'masterDecision' => $masterDecision ? [
                'id' => $masterDecision->id,
                'master_text' => $masterDecision->master_text,
                'is_submitted' => $masterDecision->is_submitted,
                'submitted_at' => $masterDecision->submitted_at?->toIso8601String(),
                'submitted_by' => $masterDecision->submittedBy ? [
                    'id' => $masterDecision->submittedBy->id,
                    'name' => $masterDecision->submittedBy->name,
                ] : null,
                'generated_by' => $masterDecision->generated_by,
                'generated_at' => $masterDecision->generated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST Generate Master Decision Summary via LLM from all session DecisionParts.
     */
    public function generateMasterDecisionSummary(Request $request, Event $event, LlmService $llmService): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        if (!$this->areAllDecisionPartsSubmittedForEvent($event)) {
            return response()->json([
                'success' => false,
                'error' => 'All conversations must have finalized Decision Parts before generating the Master Decision.',
            ], 403);
        }

        $decisionParts = DecisionPart::whereIn('conversation_id', $event->conversations()->pluck('id'))
            ->where('is_submitted', true)
            ->with('conversation')
            ->get();

        if ($decisionParts->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'No finalized Decision Parts found.'], 422);
        }

        $combinedText = $decisionParts->map(function ($part) {
            return "### Session: {$part->conversation->title}\n{$part->consolidated_text}";
        })->join("\n\n---\n\n");

        $systemPrompt = "You are the head pastoral synodal secretary. Synthesize the finalized Decision Parts from all convention sessions into a comprehensive, authoritative Master Pastoral Decision for the entire event '{$event->title}'. Produce structured Markdown with clear strategic priorities, diocesan implementations, and national resolutions.";
        $userPrompt = "Please generate the official Master Pastoral Decision from the following finalized session decision parts:\n\n{$combinedText}";

        $result = $llmService->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => 'LLM generation failed: ' . $result['error']], 500);
        }

        $master = MasterDecision::updateOrCreate(
            ['event_id' => $event->id],
            [
                'master_text' => $result['content'],
                'generated_by' => $result['provider'],
                'generated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'master_text' => $result['content'],
            'generated_by' => $result['provider'],
            'master_id' => $master->id,
        ]);
    }

    /**
     * PATCH Save Master Decision draft.
     */
    public function saveMasterDecision(Request $request, Event $event): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'master_text' => 'required|string',
        ]);

        $master = MasterDecision::updateOrCreate(
            ['event_id' => $event->id],
            ['master_text' => $validated['master_text']]
        );

        return response()->json(['success' => true, 'master' => $master]);
    }

    /**
     * POST Finalize Master Decision for the event.
     */
    public function finalizeMasterDecision(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'master_text' => 'required|string',
        ]);

        MasterDecision::updateOrCreate(
            ['event_id' => $event->id],
            [
                'master_text' => $validated['master_text'],
                'is_submitted' => true,
                'submitted_at' => now(),
                'submitted_by_user_id' => $user->id,
            ]
        );

        return back()->with('success', 'Master Pastoral Decision for the event has been finalized.');
    }

    /**
     * GET Bishop's Overview for the event.
     */
    public function bishopOverview(Request $request, Event $event): Response
    {
        $this->authorizePastoralAccess($request);

        $masterDecision = MasterDecision::where('event_id', $event->id)
            ->with('submittedBy')
            ->first();

        $conversationsWithParts = $event->conversations()
            ->with(['decisionPart' => function ($query) {
                $query->with('submittedBy');
            }])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'decision_part' => $c->decisionPart ? [
                        'id' => $c->decisionPart->id,
                        'consolidated_text' => $c->decisionPart->consolidated_text,
                        'is_submitted' => $c->decisionPart->is_submitted,
                        'submitted_at' => $c->decisionPart->submitted_at?->toIso8601String(),
                        'submitted_by' => $c->decisionPart->submittedBy?->name,
                    ] : null,
                ];
            });

        return Inertia::render('pastoral/bishop-overview', [
            'event' => $event,
            'masterDecision' => $masterDecision ? [
                'id' => $masterDecision->id,
                'master_text' => $masterDecision->master_text,
                'is_submitted' => $masterDecision->is_submitted,
                'submitted_at' => $masterDecision->submitted_at?->toIso8601String(),
                'submitted_by' => $masterDecision->submittedBy?->name,
                'generated_by' => $masterDecision->generated_by,
                'generated_at' => $masterDecision->generated_at?->toIso8601String(),
            ] : null,
            'conversationsWithParts' => $conversationsWithParts,
        ]);
    }
}
