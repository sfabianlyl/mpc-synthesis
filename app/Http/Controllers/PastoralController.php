<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\NationalDecisionDraft;
use App\Models\NationalDecisionPart;
use App\Models\Event;
use App\Models\MasterDecision;
use App\Models\Response as ResponseModel;
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
                $query->whereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->count();

        return $submittedCount >= $totalMembers;
    }
    private function areAllDecisionPartsSubmittedForEvent(Event $event): bool
    {
        $conversationIds = $event->conversations()->pluck('id');
        if ($conversationIds->isEmpty()) {
            return false;
        }


        $submittedNationalCount = NationalDecisionPart::whereIn('conversation_id', $conversationIds)
            ->where('is_submitted', true)
            ->count();

        return $submittedNationalCount === $conversationIds->count();
    }
public function index(Request $request): Response
    {
        $this->authorizePastoralAccess($request);

        $user = $request->user();
        $totalPastoralCount = $this->getTotalPastoralMembersCount();

        $conversations = Conversation::with(['event', 'masterSynthesis',  'nationalDecisionPart'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conversation) use ($user, $totalPastoralCount) {
                $masterSynthesis = $conversation->masterSynthesis;

                // Check conversation-level submission status for this user
                
                $nationalDraft = NationalDecisionDraft::where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                $userDraftSubmitted = ($nationalDraft && $nationalDraft->is_submitted);

                // Count submitted drafts for this specific conversation
                $submittedCount = 0;
                if ($totalPastoralCount > 0) {
                    $submittedCount = User::whereIn('role', ['pastoral', 'discernment'])
                        ->where(function ($query) use ($conversation) {
                            $query->whereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                                $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                            });
                        })
                        ->count();
                }

                $allDraftsSubmitted = $totalPastoralCount > 0 && $submittedCount >= $totalPastoralCount;

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
                    'national_decision_part_exists' => (bool) $nationalDecisionPart,
                    'national_decision_part_submitted' => (bool) $nationalDecisionPart?->is_submitted,
                    'decision_part_submitted' => (bool) ($nationalDecisionPart?->is_submitted),

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
     * GET Per-Conversation Decision Draft (Member Scope - conversation-level scope).
     */
    public function conversationDecision(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        // Conversation-scoped individual drafts
        $nationalDraft = NationalDecisionDraft::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['is_submitted' => false]
        );

        $allMembers = User::whereIn('role', ['pastoral', 'discernment'])
            ->get();

        // Check which members have submitted for this specific conversation
        $submittedMemberIds = User::whereIn('role', ['pastoral', 'discernment'])
            ->where(function ($query) use ($conversation) {
                $query->whereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->pluck('id')
            ->toArray();

        $eventConversations = $conversation->event
            ? $conversation->event->conversations()->select('id', 'title', 'event_id')->get()
            : collect([$conversation]);

        return Inertia::render('pastoral/decision', [
            'conversation' => $conversation->load(['event', 'masterSynthesis']),
            'eventConversations' => $eventConversations,
            'masterSynthesis' => $conversation->masterSynthesis ? [
                'id' => $conversation->masterSynthesis->id,
                'convergence_text' => $conversation->masterSynthesis->convergence_text,
                'consideration_text' => $conversation->masterSynthesis->consideration_text,
                'proposals_text' => $conversation->masterSynthesis->proposals_text,
                'other_text' => $conversation->masterSynthesis->other_text,
                'is_submitted' => $conversation->masterSynthesis->is_submitted,
                'submitted_at' => $conversation->masterSynthesis->submitted_at?->toIso8601String(),
            ] : null,
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
                    'is_submitted' => in_array($m->id, $submittedMemberIds),
                ]),
            ],
        ]);
    }

    /**
     * POST Store/Submit National Decision Draft (conversation-level scope).
     */
    public function storeConversationDraft(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'national_draft_text' => 'nullable|string',
            'national_text' => 'nullable|string',
            'is_submitted' => 'required|boolean',
        ]);
        $nationalText = $validated['national_draft_text'] ?? $validated['national_text'] ?? null;

        NationalDecisionDraft::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            [
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
                $query->whereHas('nationalDecisionDrafts', function ($q) use ($conversation) {
                    $q->where('conversation_id', $conversation->id)->where('is_submitted', true);
                });
            })
            ->count();

        // Fetch submitted national drafts for this specific conversation
        $nationalDrafts = NationalDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with('user')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'user_id' => $d->user_id,
                'draft_text' => $d->draft_text,
                'is_submitted' => $d->is_submitted,
                'submitted_at' => $d->submitted_at?->toIso8601String(),
                'user' => $d->user ? ['id' => $d->user->id, 'name' => $d->user->name, 'email' => $d->user->email] : null,
            ]);

        $nationalDecisionPart = NationalDecisionPart::where('conversation_id', $conversation->id)->first();

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
            'nationalDecisionPart' => $nationalDecisionPart ? [
                'id' => $nationalDecisionPart->id,
                'national_text' => $nationalDecisionPart->national_text,
                'is_submitted' => $nationalDecisionPart->is_submitted,
                'submitted_at' => $nationalDecisionPart->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST Generate AI summary for National Decision Part of a conversation.
     */
    public function generateDecisionPartSummary(Request $request, Conversation $conversation, LlmService $llmService)
    {
        $this->authorizePastoralAccess($request);

        $nationalDrafts = NationalDecisionDraft::where('conversation_id', $conversation->id)
            ->where('is_submitted', true)
            ->with('user')
            ->get();

        if ($nationalDrafts->isEmpty()) {
            return back()->withErrors(['error' => 'No submitted national decision drafts found for this conversation.']);
        }

        $systemPromptNational = "You are a pastoral synthesis assistant for a consultative synodal process. Synthesize the submitted individual national decision drafts into a cohesive, structured national decision part for the wider church. Highlight strategic priorities, common national themes, and actionable proposals. Format your output in clean Markdown.";
        $userPromptNational = "Please synthesize the following pastoral national submissions into a consolidated national decision part for session '{$conversation->title}':\n\n";

        foreach ($nationalDrafts as $draft) {
            if ($draft->draft_text) {
                $userPromptNational .= "[Member: {$draft->user?->name}]\n{$draft->draft_text}\n\n---\n\n";
            }
        }

        $resultNational = $llmService->query($systemPromptNational, $userPromptNational);
        if ($resultNational['success']) {
            $generatedNationalText = $resultNational['content'];
            $provider = $resultNational['provider'] ?? 'AI';
            NationalDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                ['national_text' => $generatedNationalText]
            );
        } else {
            return back()->withErrors(['error' => 'LLM generation failed: ' . $resultNational['error']]);
        }

        return back()->with('success', 'AI synthesized draft for this session successfully.');
    }

    /**
     * PATCH Save shared national decision part draft.
     */
    public function saveDecisionPart(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'national_text' => 'nullable|string',
        ]);

        if (array_key_exists('national_text', $validated)) {
            NationalDecisionPart::updateOrCreate(
                ['conversation_id' => $conversation->id],
                ['national_text' => $validated['national_text']]
            );
        }

        return back()->with('success', 'Decision Part draft saved.');
    }

    /**
     * POST Finalize shared decision parts for a conversation.
     */
    public function finalizeDecisionPart(Request $request, Conversation $conversation)
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'national_text' => 'nullable|string',
            'is_submitted' => 'nullable|boolean',
        ]);

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

        // Fetch all conversations with their national decision parts
        $conversationsWithParts = $event->conversations()
            ->with(['nationalDecisionPart'])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'description' => $c->description,
                    'status' => $c->status,
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
    public function generateMasterDecisionSummary(Request $request, Event $event, LlmService $llmService)
    {
        $this->authorizePastoralAccess($request);

        $nationalDecisionParts = NationalDecisionPart::whereIn('conversation_id', $event->conversations()->pluck('id'))
            ->where('is_submitted', true)
            ->with('conversation')
            ->get();

        if ($nationalDecisionParts->isEmpty()) {
            return back()->withErrors(['error' => 'No finalized Decision Parts found.']);
        }

        $combinedText = $nationalDecisionParts->map(function ($part) {
            return "### Session: {$part->conversation?->title}\nNational Decision Part:\n{$part->national_text}";
        })->join("\n\n---\n\n");

        $systemPrompt = "You are the head pastoral synodal secretary. Synthesize the finalized Decision Parts from all convention sessions into a comprehensive, authoritative Master Pastoral Decision for the entire event '{$event->title}'. Produce structured Markdown with clear strategic priorities and national resolutions.";
        $userPrompt = "Please generate the official Master Pastoral Decision from the following finalized session decision parts:\n\n{$combinedText}";

        $result = $llmService->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            return back()->withErrors(['error' => 'LLM generation failed: ' . $result['error']]);
        }

        MasterDecision::updateOrCreate(
            ['event_id' => $event->id],
            [
                'master_text' => $result['content'],
                'generated_by' => $result['provider'],
                'generated_at' => now(),
            ]
        );

        return back()->with('success', 'Master Pastoral Decision generated successfully.');
    }

    /**
     * PATCH Save Master Decision draft.
     */
    public function saveMasterDecision(Request $request, Event $event)
    {
        $this->authorizePastoralAccess($request);

        $validated = $request->validate([
            'master_text' => 'required|string',
        ]);

        MasterDecision::updateOrCreate(
            ['event_id' => $event->id],
            ['master_text' => $validated['master_text']]
        );

        return back()->with('success', 'Master Decision draft saved.');
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
            ->with(['nationalDecisionPart'])
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
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

    /**
     * GET View all consensus paragraph responses for a conversation.
     */
    public function consensusParagraphs(Request $request, Conversation $conversation): Response
    {
        $this->authorizePastoralAccess($request);

        $responses = ResponseModel::whereHas('conversationGroup', function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        })
        ->with(['conversationGroup.group'])
        ->get()
        ->map(function ($response) {
            return [
                'id' => $response->id,
                'group_name' => $response->conversationGroup?->group?->name ?? 'Group ' . $response->conversation_group_id,
                'consensus_text' => $response->consensus_text ?? '',
                'submitted_at' => $response->submitted_at?->toIso8601String() ?? $response->created_at?->toIso8601String(),
            ];
        })
        ->sortBy('group_name', SORT_NATURAL)
        ->values();

        return Inertia::render('pastoral/consensus-paragraphs', [
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'description' => $conversation->description,
                'status' => $conversation->status,
                'event' => $conversation->event ? [
                    'id' => $conversation->event->id,
                    'title' => $conversation->event->title,
                ] : null,
            ],
            'responses' => $responses,
        ]);
    }
}
