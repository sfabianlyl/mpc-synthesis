<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\DioceseDecisionDraft;
use App\Models\DioceseDecisionPart;
use App\Models\NationalDecisionDraft;
use App\Models\NationalDecisionPart;
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
     * Check if all pastoral members have submitted their drafts for a given conversation.
     */
    private function areAllDraftsSubmittedForConversation(Conversation $conversation): bool
    {
        $totalMembers = $this->getTotalPastoralMembersCount();
        if ($totalMembers === 0) {
            return false;
        }

        $submittedCount = User::whereIn('role', ['pastoral', 'discernment'])
            ->where(function ($query) use ($conversation) {
                $query->whereHas('dioceseDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                })
                ->orWhereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->count();

        return $submittedCount >= $totalMembers;
    }

    /**
     * Check if all conversations for an event have their diocesan and national decision parts submitted.
     */
    private function areAllDecisionPartsSubmittedForEvent(Event $event): bool
    {
        $conversationIds = $event->conversations()->pluck('id');
        if ($conversationIds->isEmpty()) {
            return false;
        }

        $submittedDioceseCount = DioceseDecisionPart::whereIn('conversation_id', $conversationIds)
            ->where('is_submitted', true)
            ->count();

        $submittedNationalCount = NationalDecisionPart::whereIn('conversation_id', $conversationIds)
            ->where('is_submitted', true)
            ->count();

        return $submittedDioceseCount === $conversationIds->count() && $submittedNationalCount === $conversationIds->count();
    }

    /**
     * Returns pastoral dashboard view with per-conversation and event progress.
     */
    public function index(Request $request): Response
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();
        $totalPastoralCount = $this->getTotalPastoralMembersCount();

        $conversations = Conversation::with(['event', 'masterSynthesis', 'dioceseDecisionPart', 'nationalDecisionPart'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($user, $totalPastoralCount) {
                $masterSynthesis = $conversation->masterSynthesis;

                // Check conversation-level submission status for this user
                $dioceseDraft = DioceseDecisionDraft::where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->first();
                $nationalDraft = NationalDecisionDraft::where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                $userDraftSubmitted = ($dioceseDraft && $dioceseDraft->is_submitted) ||
                                      ($nationalDraft && $nationalDraft->is_submitted);

                // Count submitted drafts for this specific conversation
                $submittedCount = 0;
                if ($totalPastoralCount > 0) {
                    $submittedCount = User::whereIn('role', ['pastoral', 'discernment'])
                        ->where(function ($query) use ($conversation) {
                            $query->whereHas('dioceseDecisionDrafts', function ($q) use ($conversation) {
                                $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                            })
                            ->orWhereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                                $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                            });
                        })
                        ->count();
                }

                $allDraftsSubmitted = $totalPastoralCount > 0 && $submittedCount >= $totalPastoralCount;

                $dioceseDecisionPart = $conversation->dioceseDecisionPart;
                $nationalDecisionPart = $conversation->nationalDecisionPart;

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
                    'has_master_synthesis' => $masterSynthesis !== null,

                    // Stage 1: Individual Draft (conversation-level scope)
                    'user_draft_submitted' => (bool) $userDraftSubmitted,
                    'submitted_drafts_count' => $submittedCount,
                    'total_pastoral_count' => $totalPastoralCount,
                    'all_drafts_submitted' => $allDraftsSubmitted,

                    // Stage 2: Shared Decision Parts (split per level)
                    'diocese_decision_part_exists' => (bool) $dioceseDecisionPart,
                    'diocese_decision_part_submitted' => (bool) $dioceseDecisionPart?->is_submitted,
                    'national_decision_part_exists' => (bool) $nationalDecisionPart,
                    'national_decision_part_submitted' => (bool) $nationalDecisionPart?->is_submitted,
                    'decision_part_submitted' => (bool) ($dioceseDecisionPart?->is_submitted && $nationalDecisionPart?->is_submitted),

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
     * GET Per-Conversation Decision Draft (Member Scope - conversation-level scope).
     */
    public function conversationDecision(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        // Conversation-scoped individual drafts
        $dioceseDraft = DioceseDecisionDraft::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['diocese_id' => $user->diocese_id, 'is_submitted' => false]
        );

        $nationalDraft = NationalDecisionDraft::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['diocese_id' => $user->diocese_id, 'is_submitted' => false]
        );

        $allMembers = User::whereIn('role', ['pastoral', 'discernment'])
            ->with('diocese')
            ->get();

        // Check which members have submitted for this specific conversation
        $submittedMemberIds = User::whereIn('role', ['pastoral', 'discernment'])
            ->where(function ($query) use ($conversation) {
                $query->whereHas('dioceseDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                })
                ->orWhereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->pluck('id')
            ->toArray();

        $eventConversations = $conversation->event
            ? $conversation->event->conversations()->select('id', 'title', 'event_id')->get()
            : collect([$conversation]);

        return Inertia::render('pastoral/decision', [
            'conversation' => $conversation->load('event'),
            'eventConversations' => $eventConversations,
            'dioceseDraft' => [
                'id' => $dioceseDraft->id,
                'draft_text' => $dioceseDraft->draft_text,
                'is_submitted' => $dioceseDraft->is_submitted,
                'submitted_at' => $dioceseDraft->submitted_at?->toIso8601String(),
            ],
            'nationalDraft' => [
                'id' => $nationalDraft->id,
                'draft_text' => $nationalDraft->draft_text,
                'is_submitted' => $nationalDraft->is_submitted,
                'submitted_at' => $nationalDraft->submitted_at?->toIso8601String(),
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
     * POST Store/Submit Diocese and National Decision Drafts (conversation-level scope).
     */
    public function storeConversationDraft(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'diocese_draft_text' => 'nullable|string',
            'national_draft_text' => 'nullable|string',
            'diocesan_text' => 'nullable|string',
            'national_text' => 'nullable|string',
            'is_submitted' => 'required|boolean',
        ]);

        $dioceseText = $validated['diocese_draft_text'] ?? $validated['diocesan_text'] ?? null;
        $nationalText = $validated['national_draft_text'] ?? $validated['national_text'] ?? null;

        DioceseDecisionDraft::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
                'diocese_id' => $user->diocese_id,
                'draft_text' => $dioceseText,
                'is_submitted' => $validated['is_submitted'],
                'submitted_at' => $validated['is_submitted'] ? now() : null,
            ]
        );

        NationalDecisionDraft::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
                'diocese_id' => $user->diocese_id,
                'draft_text' => $nationalText,
                'is_submitted' => $validated['is_submitted'],
                'submitted_at' => $validated['is_submitted'] ? now() : null,
            ]
        );

        return back()->with('success', $validated['is_submitted']
            ? 'Decision drafts submitted successfully.'
            : 'Drafts saved.');
    }

    /**
     * GET Collaborate on Shared Decision Parts for a Conversation.
     */
    public function collaborateConversation(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);

        // Submission progress (informational only — always accessible)
        $totalMembers = $this->getTotalPastoralMembersCount();

        // Count submitted drafts for this conversation
        $submittedDraftsCount = User::whereIn('role', ['pastoral', 'discernment'])
            ->where(function ($query) use ($conversation) {
                $query->whereHas('dioceseDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                })
                ->orWhereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->count();

        // Fetch submitted drafts for this specific conversation
        $nationalDrafts = NationalDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'user_id' => $d->user_id,
                'draft_text' => $d->draft_text,
                'is_submitted' => $d->is_submitted,
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email] : null,
                'diocese' => $d->diocese ? ['id' => $d->diocese->id, 'name' => $d->diocese->name, 'code' => $d->diocese->code] : null,
            ]);

        $dioceseDrafts = DioceseDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'user_id' => $d->user_id,
                'draft_text' => $d->draft_text,
                'is_submitted' => $d->is_submitted,
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email] : null,
                'diocese' => $d->diocese ? ['id' => $d->diocese->id, 'name' => $d->diocese->name, 'code' => $d->diocese->code] : null,
            ]);

        // Fetch shared diocese decision part (exactly ONE per conversation)
        $dioceseDecisionPart = DioceseDecisionPart::where('conversation_id', $conversation->id)->first();

        // Fetch shared national decision part (exactly ONE per conversation)
        $nationalDecisionPart = NationalDecisionPart::where('conversation_id', $conversation->id)->first();

        // Conversations in the same event for navigation
        $eventConversations = $conversation->event
            ? $conversation->event->conversations()->select('id', 'title', 'event_id')->get()
            : collect([$conversation]);

        return Inertia::render('pastoral/collaborate', [
            'conversation' => $conversation->load('event'),
            'eventConversations' => $eventConversations,
            'progress' => [
                'submitted_count' => $submittedDraftsCount,
                'total_required' => $totalMembers,
            ],
            'nationalDrafts' => $nationalDrafts,
            'dioceseDrafts' => $dioceseDrafts,
            'dioceseDecisionPart' => $dioceseDecisionPart ? [
                'id' => $dioceseDecisionPart->id,
                'diocesan_text' => $dioceseDecisionPart->diocesan_text,
                'is_submitted' => $dioceseDecisionPart->is_submitted,
                'submitted_at' => $dioceseDecisionPart->submitted_at?->toIso8601String(),
            ] : null,
            'nationalDecisionPart' => $nationalDecisionPart ? [
                'id' => $nationalDecisionPart->id,
                'national_text' => $nationalDecisionPart->national_text,
                'is_submitted' => $nationalDecisionPart->is_submitted,
                'submitted_at' => $nationalDecisionPart->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST Generate Decision Part Summary with LLM for Diocesan and/or National levels.
     */
    public function generateDecisionPartSummary(Request $request, Conversation $conversation, LlmService $llmService): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        // Gather submitted drafts for this conversation
        $dioceseDrafts = DioceseDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get();

        $nationalDrafts = NationalDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with(['user', 'diocese'])
            ->get();

        if ($dioceseDrafts->isEmpty() && $nationalDrafts->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'No submitted drafts found for this conversation.'], 422);
        }

        $generatedDiocesanText = null;
        $generatedNationalText = null;
        $provider = 'AI';

        // 1. Synthesize Diocesan Decision Part if diocese drafts exist
        if (!$dioceseDrafts->isEmpty()) {
            $systemPromptDiocese = "You are a pastoral synthesis assistant for a consultative synodal process. Synthesize the submitted individual diocesan decision drafts into a cohesive, structured diocesan decision part. Group by diocese if relevant, preserve critical consensus points, key concerns, and concrete recommendations. Format your output in clean Markdown.";
            $userPromptDiocese = "Please synthesize the following pastoral diocesan submissions into a consolidated diocesan decision part for session '{$conversation->title}':\n\n";

            foreach ($dioceseDrafts as $draft) {
                if ($draft->draft_text) {
                    $userPromptDiocese .= "[Member: {$draft->user?->name}" . ($draft->diocese ? ", Diocese: {$draft->diocese->name}" : '') . "]\n{$draft->draft_text}\n\n---\n\n";
                }
            }

            $resultDiocese = $llmService->query($systemPromptDiocese, $userPromptDiocese);
            if ($resultDiocese['success']) {
                $generatedDiocesanText = $resultDiocese['content'];
                $provider = $resultDiocese['provider'] ?? $provider;
                DioceseDecisionPart::updateOrCreate(
                    ['conversation_id' => $conversation->id],
                    ['diocesan_text' => $generatedDiocesanText]
                );
            }
        }

        // 2. Synthesize National Decision Part if national drafts exist
        if (!$nationalDrafts->isEmpty()) {
            $systemPromptNational = "You are a pastoral synthesis assistant for a consultative synodal process. Synthesize the submitted individual national decision drafts into a cohesive, structured national decision part for the wider church. Highlight strategic priorities, common national themes, and actionable proposals. Format your output in clean Markdown.";
            $userPromptNational = "Please synthesize the following pastoral national submissions into a consolidated national decision part for session '{$conversation->title}':\n\n";

            foreach ($nationalDrafts as $draft) {
                if ($draft->draft_text) {
                    $userPromptNational .= "[Member: {$draft->user?->name}" . ($draft->diocese ? ", Diocese: {$draft->diocese->name}" : '') . "]\n{$draft->draft_text}\n\n---\n\n";
                }
            }

            $resultNational = $llmService->query($systemPromptNational, $userPromptNational);
            if ($resultNational['success']) {
                $generatedNationalText = $resultNational['content'];
                $provider = $resultNational['provider'] ?? $provider;
                NationalDecisionPart::updateOrCreate(
                    ['conversation_id' => $conversation->id],
                    ['national_text' => $generatedNationalText]
                );
            }
        }

        return response()->json([
            'success' => true,
            'diocesan_text' => $generatedDiocesanText,
            'national_text' => $generatedNationalText,
            'generated_by' => $provider,
        ]);
    }

    /**
     * PATCH Save shared diocese and/or national decision part drafts.
     */
    public function saveDecisionPart(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'diocesan_text' => 'nullable|string',
            'national_text' => 'nullable|string',
            'consolidated_text' => 'nullable|string',
        ]);

        if (array_key_exists('diocesan_text', $validated)) {
            DioceseDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                ['diocesan_text' => $validated['diocesan_text']]
            );
        }

        if (array_key_exists('national_text', $validated)) {
            NationalDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                ['national_text' => $validated['national_text']]
            );
        }

        $diocesePart = DioceseDecisionPart::where('conversation_id', $conversation->id)->first();
        $nationalPart = NationalDecisionPart::where('conversation_id', $conversation->id)->first();

        return response()->json([
            'success' => true,
            'diocese_decision_part' => $diocesePart,
            'national_decision_part' => $nationalPart,
        ]);
    }

    /**
     * POST Finalize shared decision parts for a conversation.
     */
    public function finalizeDecisionPart(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'diocesan_text' => 'nullable|string',
            'national_text' => 'nullable|string',
            'is_submitted' => 'nullable|boolean',
        ]);

        if (isset($validated['diocesan_text'])) {
            DioceseDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'diocesan_text' => $validated['diocesan_text'],
                    'is_submitted' => true,
                    'submitted_at' => now(),
                ]
            );
        } else {
            DioceseDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'is_submitted' => true,
                    'submitted_at' => now(),
                ]
            );
        }

        if (isset($validated['national_text'])) {
            NationalDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'national_text' => $validated['national_text'],
                    'is_submitted' => true,
                    'submitted_at' => now(),
                ]
            );
        } else {
            NationalDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                [
                    'is_submitted' => true,
                    'submitted_at' => now(),
                ]
            );
        }

        return back()->with('success', "Decision Parts for '{$conversation->title}' have been finalized.");
    }

    /**
     * GET Event-Level Master Decision Review Page.
     */
    public function masterDecision(Request $request, Event $event): Response
    {
        $this->authorizePastoralAccess($request);

        // Submission progress (informational only — always accessible)

        // Fetch all conversations with their diocese and national decision parts
        $conversationsWithParts = $event->conversations()
            ->with(['dioceseDecisionPart', 'nationalDecisionPart'])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'status' => $c->status,
                    'diocese_decision_part' => $c->dioceseDecisionPart ? [
                        'id' => $c->dioceseDecisionPart->id,
                        'diocesan_text' => $c->dioceseDecisionPart->diocesan_text,
                        'is_submitted' => $c->dioceseDecisionPart->is_submitted,
                        'submitted_at' => $c->dioceseDecisionPart->submitted_at?->toIso8601String(),
                    ] : null,
                    'national_decision_part' => $c->nationalDecisionPart ? [
                        'id' => $c->nationalDecisionPart->id,
                        'national_text' => $c->nationalDecisionPart->national_text,
                        'is_submitted' => $c->nationalDecisionPart->is_submitted,
                        'submitted_at' => $c->nationalDecisionPart->submitted_at?->toIso8601String(),
                    ] : null,
                ];
            });

        $masterDecision = MasterDecision::where('event_id', $event->id)
            ->with('submittedBy')
            ->first();

        return Inertia::render('pastoral/master-decision', [
            'event' => $event,
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

        // Use diocese decision parts for the master synthesis
        $dioceseDecisionParts = DioceseDecisionPart::whereIn('conversation_id', $event->conversations()->pluck('id'))
            ->where('is_submitted', true)
            ->get();

        $nationalDecisionParts = NationalDecisionPart::whereIn('conversation_id', $event->conversations()->pluck('id'))
            ->where('is_submitted', true)
            ->get();

        if ($dioceseDecisionParts->isEmpty() && $nationalDecisionParts->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'No finalized Decision Parts found.'], 422);
        }

        $combinedText = $dioceseDecisionParts->map(function ($part) {
            return "### Session: {$part->conversation?->title}\nDiocesan Decision Part:\n{$part->diocesan_text}";
        })->join("\n\n---\n\n");

        // Also add national decision parts
        if (!$nationalDecisionParts->isEmpty()) {
            $nationalText = $nationalDecisionParts->map(function ($part) {
                return "### Session: {$part->conversation?->title}\nNational Decision Part:\n{$part->national_text}";
            })->join("\n\n---\n\n");

            if (!empty($combinedText)) {
                $combinedText .= "\n\n---\n\n# National Decision Parts\n\n{$nationalText}";
            } else {
                $combinedText = $nationalText;
            }
        }

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
            ->with(['dioceseDecisionPart', 'nationalDecisionPart'])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'diocese_decision_part' => $c->dioceseDecisionPart ? [
                        'id' => $c->dioceseDecisionPart->id,
                        'diocesan_text' => $c->dioceseDecisionPart->diocesan_text,
                        'is_submitted' => $c->dioceseDecisionPart->is_submitted,
                        'submitted_at' => $c->dioceseDecisionPart->submitted_at?->toIso8601String(),
                    ] : null,
                    'national_decision_part' => $c->nationalDecisionPart ? [
                        'id' => $c->nationalDecisionPart->id,
                        'national_text' => $c->nationalDecisionPart->national_text,
                        'is_submitted' => $c->nationalDecisionPart->is_submitted,
                        'submitted_at' => $c->nationalDecisionPart->submitted_at?->toIso8601String(),
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

