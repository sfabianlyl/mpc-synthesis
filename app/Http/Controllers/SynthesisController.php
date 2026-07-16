<?php

namespace App\Http\Controllers;

use App\Broadcasting\Events\MasterSynthesisUpdated;
use App\Broadcasting\Events\SynthesisSubmitted;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\Response;
use App\Models\SynthesisCustomQuery;
use App\Models\SynthesisReport;
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
        $report = SynthesisReport::where('conversation_id', $selectedConversationId)
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

        // Fetch ALL groups for this conversation (not just assigned groups)
        $allGroupsForConversation = Group::whereHas('responses', function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        })->with(['responses' => function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        }])->get();

        // Build groups structure with responses from ALL synthesis members assigned to this conversation
        $groups = $allGroupsForConversation->map(function ($group) {
            return (object)[
                'id' => $group->id,
                'name' => $group->name,
                'responses' => $group->responses,
            ];
        });

        // Fetch or create a draft report so user inputs can be saved
        $report = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->with('customQueries')
            ->first();

        // Auto-create draft report if none exists, so manual inputs have a record to update
        if (!$report) {
            $report = SynthesisReport::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => SynthesisReport::getNextSubmissionNumber($conversation->id, $user->id),
                'status' => 'draft',
                'is_llm_assisted' => false,
            ]);
        }

        // Fetch consensus submission status from all groups for this conversation
        $consensusSubmissions = [];
        if ($allGroupsForConversation->isNotEmpty()) {
            $groupIds = $allGroupsForConversation->pluck('id');
            $responses = Response::whereIn('group_id', $groupIds)
                ->where('conversation_id', $conversation->id)
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

        return Inertia::render('synthesis/review', [
            'conversation' => $conversation,
            'group' => (object)[
                'id' => $conversation->id,
                'name' => $conversation->title,
            ],
            'groups' => $groups,
            'responses' => $groups->flatMap->responses,
            'report' => $report,
            'consensusSubmissions' => $consensusSubmissions,
        ]);
    }

    /**
     * Trigger LLM analysis for selected responses of groups in a session.
     */
    public function analyze(Request $request, Conversation $conversation, LlmService $llm)
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

        // Get all group IDs that have responses in ANY of the user's assigned conversations
        $assignedConversationIds = $user->assignedConversations()->pluck('conversations.id');

        // Collect responses, filtered by selected groups if provided
        if ($selectedGroupIds && count($selectedGroupIds) > 0) {
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', $selectedGroupIds)
                ->with('group')
                ->get();
        } else {
            // Get all responses from the selected conversation that belong to groups in assigned conversations
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', function ($query) use ($assignedConversationIds) {
                    $query->select('group_id')
                        ->from('responses')
                        ->whereIn('conversation_id', $assignedConversationIds)
                        ->distinct();
                })
                ->with('group')
                ->get();
        }

        if ($responses->isEmpty()) {
            return back()->withErrors(['analysis' => 'No responses have been submitted by your assigned groups for this session.']);
        }

        // Combine all responses into a prompt format
        $combinedText = $responses->map(function ($resp, $index) {
            return "Group: {$resp->group->name}\nResponse:\n{$resp->consensus_text}";
        })->implode("\n\n---\n\n");

        $result = $llm->query(
            $llm->buildSynthesisPrompt(),
            $combinedText
        );

        if (! $result['success']) {
            Log::error('LLM analysis failed.', ['error' => $result['error'], 'provider' => $result['provider']]);
            return back()->withErrors(['analysis' => 'LLM analysis failed: '.$result['error']]);
        }

        // Parse the JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            Log::warning('LLM returned non-JSON response, using raw text.', [
                'content' => $result['content'],
            ]);
            $parsed = [
                'convergence' => $result['content'],
                'consideration' => '',
                'proposals' => '',
            ];
        } else {
            $parsed = $content;
        }

        // Normalize text values
        if (is_array($parsed)) {
            foreach ($parsed as $key => $value) {
                if (is_string($value)) {
                    $parsed[$key] = $this->normalizeLlmText($value);
                }
            }
        }

        // Create or update the synthesis report (save both AI output and manual text inputs)
        // Only save manual text fields if they have non-empty values to avoid overwriting existing content
        $updateData = [
            // AI-generated fields (read-only on frontend, cannot be edited by synthesis member)
            'ai_convergence_text' => $parsed['convergence'] ?? $parsed['convergence_text'] ?? '',
            'ai_consideration_text' => $parsed['consideration'] ?? $parsed['consideration_text'] ?? '',
            'ai_proposals_text' => $parsed['proposals'] ?? $parsed['proposals_text'] ?? '',
            'ai_source_group_ids' => $selectedGroupIds ?? $responses->pluck('group_id')->unique()->values()->toArray(),
            'ai_generated_at' => now(),
            'llm_generated' => true,
            'status' => 'draft',
        ];

        // Only update manual text fields if non-empty values are provided
        $manualConvergence = $request->input('convergence_text');
        if ($manualConvergence !== null && $manualConvergence !== '') {
            $updateData['convergence_text'] = $manualConvergence;
        }

        $manualConsideration = $request->input('consideration_text');
        if ($manualConsideration !== null && $manualConsideration !== '') {
            $updateData['consideration_text'] = $manualConsideration;
        }

        $manualProposals = $request->input('proposals_text');
        if ($manualProposals !== null && $manualProposals !== '') {
            $updateData['proposals_text'] = $manualProposals;
        }

        $report = SynthesisReport::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
            ],
            $updateData
        );

        // Calculate group count for feedback
        $groupCount = $selectedGroupIds ? count($selectedGroupIds) : $responses->count();

        Log::info('LLM analysis: report created/updated.', [
            'report_id' => $report->id,
            'ai_convergence_text_length' => strlen($report->ai_convergence_text ?? ''),
            'ai_consideration_text_length' => strlen($report->ai_consideration_text ?? ''),
            'ai_proposals_text_length' => strlen($report->ai_proposals_text ?? ''),
            'llm_generated' => $report->llm_generated,
            'status' => $report->status,
            'group_count' => $groupCount,
        ]);

        // Fetch groups and consensus submissions for the review page (same as review() method)
        $allGroupsForConversation = Group::whereHas('responses', function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        })->with(['responses' => function ($query) use ($conversation) {
            $query->where('conversation_id', $conversation->id);
        }])->get();

        $groups = $allGroupsForConversation->map(function ($group) {
            return (object)[
                'id' => $group->id,
                'name' => $group->name,
                'responses' => $group->responses,
            ];
        });

        $consensusSubmissions = [];
        if ($allGroupsForConversation->isNotEmpty()) {
            $groupIds = $allGroupsForConversation->pluck('id');
            $submissionResponses = Response::whereIn('group_id', $groupIds)
                ->where('conversation_id', $conversation->id)
                ->with('group')
                ->get();

            $consensusSubmissions = $submissionResponses->groupBy('group_id')->map(function ($groupResponses) {
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

        // Use back() redirect instead of Inertia::render to prevent URL navigation.
        // When Inertia receives a rendered page from a POST, it navigates to the request URL.
        // A back() redirect keeps the current URL intact while passing data via session flash.
        return back()->with([
            'success' => 'LLM analysis completed successfully for '.$groupCount.' group(s) using '.$result['provider'].' provider.',
            'report' => [
                'id' => $report->id,
                'conversation_id' => $report->conversation_id,
                'synthesis_id' => $report->synthesis_id,
                'convergence_text' => $report->convergence_text,
                'consideration_text' => $report->consideration_text,
                'proposals_text' => $report->proposals_text,
                'ai_convergence_text' => $report->ai_convergence_text,
                'ai_consideration_text' => $report->ai_consideration_text,
                'ai_proposals_text' => $report->ai_proposals_text,
                'ai_source_group_ids' => $report->ai_source_group_ids,
                'ai_generated_at' => $report->ai_generated_at?->toIso8601String(),
                'llm_generated' => $report->llm_generated,
                'status' => $report->status,
                'custom_queries' => $report->customQueries->map(fn($q) => [
                    'id' => $q->id,
                    'prompt' => $q->prompt,
                    'response' => $q->response,
                    'provider' => $q->provider,
                    'created_at' => $q->created_at->toIso8601String(),
                ])->toArray(),
            ],
            'provider' => $result['provider'],
        ]);
    }

    /**
     * Handle a custom LLM query from the synthesis review page.
     * 
     * The synthesis member can use custom prompts for synthesis focus areas not covered by the 3 main categories.
     * The responses cannot be edited, but can be deleted.
     */
    public function customQuery(Request $request, Conversation $conversation, LlmService $llm)
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
        $report = SynthesisReport::firstOrCreate(
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

        $combinedText = $responses->map(fn($resp) =>
            "Group: {$resp->group->name}\nResponse:\n{$resp->consensus_text}"
        )->implode("\n\n---\n\n");

        $result = $llm->query(
            $llm->buildCustomQueryPrompt(),
            "Context:\n{$combinedText}\n\nUser Question:\n{$validated['prompt']}"
        );

        if (!$result['success']) {
            return back()->withErrors(['custom_query' => 'LLM query failed: ' . $result['error']]);
        }

        // Parse response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            Log::warning('Custom query LLM returned non-JSON response, using raw text.', [
                'content' => $result['content'],
            ]);
            $bulletPoints = [$result['content']];
        } else {
            $bulletPoints = $content['points'] ?? $content['bullets'] ?? [$result['content']];
        }

        // Save as custom query (stored separately, cannot be edited, can be deleted)
        SynthesisCustomQuery::create([
            'synthesis_report_id' => $report->id,
            'prompt' => $validated['prompt'],
            'response' => $bulletPoints,
            'provider' => $result['provider'],
        ]);

        return back()->with('success', 'Custom query completed successfully.');
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
    public function update(Request $request, SynthesisReport $report)
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
            'status' => 'nullable|in:draft,reviewed,submitted',
        ]);

        $report->update($validated);

        return back()->with('success', 'Report updated successfully.');
    }

    /**
     * Submit the synthesis report for compilation.
     */
    public function submit(Request $request, SynthesisReport $report)
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

        // Fetch all submissions for this conversation
        $submissions = SynthesisReport::where('conversation_id', $conversation->id)
            ->with(['synthesis', 'customQueries'])
            ->orderBy('submission_number')
            ->get();

        $submissions = $submissions->map(function ($s) {
            return [
                'id' => $s->id,
                'submission_number' => $s->submission_number,
                'user' => [
                    'id' => $s->synthesis->id,
                    'name' => $s->synthesis->name,
                    'email' => $s->synthesis->email,
                ],
                'convergence_text' => $s->convergence_text,
                'consideration_text' => $s->consideration_text,
                'proposals_text' => $s->proposals_text,
                'other_text' => $s->other_text,
                'ai_convergence_text' => $s->ai_convergence_text,
                'ai_consideration_text' => $s->ai_consideration_text,
                'ai_proposals_text' => $s->ai_proposals_text,
                'ai_other_text' => $s->ai_other_text,
                'is_llm_assisted' => $s->is_llm_assisted,
                'status' => $s->status,
                'submitted_at' => $s->submitted_at?->toIso8601String(),
                'custom_queries' => $s->customQueries->map(fn($q) => [
                    'id' => $q->id,
                    'prompt' => $q->prompt,
                    'response' => $q->response,
                    'provider' => $q->provider,
                    'created_at' => $q->created_at->toIso8601String(),
                ]),
            ];
        });

        // Fetch master synthesis (latest submitted report for this conversation)
        $masterSynthesis = SynthesisReport::where('conversation_id', $conversation->id)
            ->whereIn('status', ['submitted', 'reviewed'])
            ->orderBy('submission_number', 'desc')
            ->first();

        $masterSynthesisData = $masterSynthesis ? [
            'id' => $masterSynthesis->id,
            'convergence_text' => $masterSynthesis->convergence_text,
            'consideration_text' => $masterSynthesis->consideration_text,
            'proposals_text' => $masterSynthesis->proposals_text,
            'other_text' => $masterSynthesis->other_text,
            'llm_generated' => $masterSynthesis->llm_generated,
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

        return Inertia::render('synthesis/review-page', [
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
     * - Manual inputs by category (convergence_text, consideration_text, proposals_text)
     * - AI-generated synthesis by category (ai_convergence_text, ai_consideration_text, ai_proposals_text)
     * - Custom prompts and their responses in the "Others" category (other_text)
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
            'ai_convergence_text' => 'nullable|string',
            'ai_consideration_text' => 'nullable|string',
            'ai_proposals_text' => 'nullable|string',
        ]);

        // Check if user already has a submitted submission for this conversation
        $existingSubmitted = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('status', 'submitted')
            ->first();

        if ($existingSubmitted) {
            return back()->withErrors([
                'submission' => 'You have already submitted your synthesis for this conversation. Please start a new submission cycle.',
            ]);
        }

        // Find the existing draft to get its ID for custom query cleanup
        $existingDraft = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft) {
            // Update existing draft with all data (manual inputs + AI data + custom queries as Others)
            $updateData = array_merge($validated, [
                'status' => 'submitted',
                'submitted_at' => now(),
                'is_llm_assisted' => false,
            ]);
            $existingDraft->update($updateData);
            $report = $existingDraft->fresh();
        } else {
            // Create new submission with next submission_number
            $nextNumber = SynthesisReport::getNextSubmissionNumber($conversation->id, $user->id);
            $createData = array_merge($validated, [
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => $nextNumber,
                'status' => 'submitted',
                'submitted_at' => now(),
                'is_llm_assisted' => false,
            ]);
            $report = SynthesisReport::create($createData);
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
     * Generate AI-assisted synthesis for the current user's draft.
     */
    public function generateAiAssistedSynthesis(Request $request, Conversation $conversation, LlmService $llm)
    {
        $user = $request->user();

        // Authorization
        $conversationIds = $user->assignedConversations()->pluck('conversations.id');
        if (!$user->isAdmin() && !$conversationIds->contains($conversation->id)) {
            abort(403, 'You are not assigned to this conversation.');
        }

        // Get selected group IDs from request
        $selectedGroupIds = $request->input('selected_group_ids');

        // Collect responses filtered by selected groups if provided
        if ($selectedGroupIds && count($selectedGroupIds) > 0) {
            $responses = Response::where('conversation_id', $conversation->id)
                ->whereIn('group_id', $selectedGroupIds)
                ->with('group')
                ->get();
        } else {
            $responses = Response::where('conversation_id', $conversation->id)
                ->with('group')
                ->get();
        }

        if ($responses->isEmpty()) {
            return back()->withErrors(['ai_synthesis' => 'No responses available for AI synthesis.']);
        }

        // Combine all responses into a prompt format
        $combinedText = $responses->map(function ($resp) {
            return "Group: {$resp->group->name}\nResponse:\n{$resp->consensus_text}";
        })->implode("\n\n---\n\n");

        $result = $llm->query(
            $llm->buildSynthesisPrompt(),
            $combinedText
        );

        if (!$result['success']) {
            Log::error('AI synthesis failed.', ['error' => $result['error'], 'provider' => $result['provider']]);
            return back()->withErrors(['ai_synthesis' => 'AI synthesis failed: ' . $result['error']]);
        }

        // Parse the JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            Log::warning('AI synthesis returned non-JSON response, using raw text.', [
                'content' => $result['content'],
            ]);
            $parsed = [
                'convergence' => $result['content'],
                'consideration' => '',
                'proposals' => '',
                'other' => '',
            ];
        } else {
            $parsed = array_merge([
                'convergence' => '',
                'consideration' => '',
                'proposals' => '',
                'other' => '',
            ], $content);
        }

        // Normalize text values
        foreach ($parsed as $key => $value) {
            if (is_string($value)) {
                $parsed[$key] = $this->normalizeLlmText($value);
            }
        }

        // Find or create a draft submission for the current user
        $report = SynthesisReport::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'status' => 'draft',
            ],
            [
                'submission_number' => SynthesisReport::getNextSubmissionNumber($conversation->id, $user->id),
            ]
        );

        // Store AI results in ai_* columns (not the regular text columns)
        $report->update([
            'ai_convergence_text' => $parsed['convergence'] ?? '',
            'ai_consideration_text' => $parsed['consideration'] ?? '',
            'ai_proposals_text' => $parsed['proposals'] ?? '',
            'ai_other_text' => $parsed['other'] ?? '',
            'ai_source_group_ids' => $selectedGroupIds ?? $responses->pluck('group_id')->unique()->values()->toArray(),
            'ai_generated_at' => now(),
            'is_llm_assisted' => true,
        ]);

        return back()->with('success', 'AI-assisted synthesis generated successfully using ' . $result['provider'] . ' provider.');
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

        // Validate all 4 categories have at least one content source (ai or manual)
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.convergence' => 'required|array',
            'categories.convergence.ai_content' => 'nullable|string',
            'categories.convergence.manual_content' => 'nullable|string',
            'categories.matters_for_consideration' => 'required|array',
            'categories.matters_for_consideration.ai_content' => 'nullable|string',
            'categories.matters_for_consideration.manual_content' => 'nullable|string',
            'categories.proposals' => 'required|array',
            'categories.proposals.ai_content' => 'nullable|string',
            'categories.proposals.manual_content' => 'nullable|string',
            'categories.others' => 'required|array',
            'categories.others.ai_content' => 'nullable|string',
            'categories.others.manual_content' => 'nullable|string',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer',
        ]);

        // Ensure each category has at least one content source
        foreach (['convergence', 'matters_for_consideration', 'proposals', 'others'] as $category) {
            $aiContent = $validated['categories'][$category]['ai_content'] ?? '';
            $manualContent = $validated['categories'][$category]['manual_content'] ?? '';
            if (empty($aiContent) && empty($manualContent)) {
                return back()->withErrors([
                    "categories.{$category}" => "The {$category} category must have either AI-generated or manual content.",
                ]);
            }
        }

        // Find or create the master synthesis report for this conversation
        $report = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        if (!$report) {
            $report = SynthesisReport::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => SynthesisReport::getNextSubmissionNumber($conversation->id, $user->id),
                'is_master_synthesis' => true,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        // Map category names to database field names
        $categoryMap = [
            'convergence' => ['ai_field' => 'ai_convergence_text', 'manual_field' => 'convergence_text'],
            'matters_for_consideration' => ['ai_field' => 'ai_consideration_text', 'manual_field' => 'consideration_text'],
            'proposals' => ['ai_field' => 'ai_proposals_text', 'manual_field' => 'proposals_text'],
            'others' => ['ai_field' => 'ai_other_text', 'manual_field' => 'other_text'],
        ];

        $updateData = [];
        $hasAiContent = false;

        foreach ($categoryMap as $categoryKey => $fields) {
            $aiContent = $validated['categories'][$categoryKey]['ai_content'] ?? '';
            $manualContent = $validated['categories'][$categoryKey]['manual_content'] ?? '';

            if (!empty($aiContent)) {
                $updateData[$fields['ai_field']] = $aiContent;
                $hasAiContent = true;
            }
            if (!empty($manualContent)) {
                $updateData[$fields['manual_field']] = $manualContent;
            }
        }

        // Track which group consensuses were included
        if (isset($validated['group_ids']) && !empty($validated['group_ids'])) {
            $updateData['group_ids'] = $validated['group_ids'];
        }

        // Update AI-assisted flag
        if ($hasAiContent) {
            $updateData['is_llm_assisted'] = true;
            $updateData['ai_generated_at'] = now();
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
                'is_llm_assisted' => $report->is_llm_assisted,
                'categories' => [
                    'convergence' => ['ai_content' => $report->ai_convergence_text ?? '', 'manual_content' => $report->convergence_text ?? ''],
                    'matters_for_consideration' => ['ai_content' => $report->ai_consideration_text ?? '', 'manual_content' => $report->consideration_text ?? ''],
                    'proposals' => ['ai_content' => $report->ai_proposals_text ?? '', 'manual_content' => $report->proposals_text ?? ''],
                    'others' => ['ai_content' => $report->ai_other_text ?? '', 'manual_content' => $report->other_text ?? ''],
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
        $masterSynthesis = SynthesisReport::where('conversation_id', $conversation->id)
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
                'is_llm_assisted' => $masterSynthesis->is_llm_assisted,
                'group_ids' => $masterSynthesis->group_ids ?? [],
                'submitted_at' => $masterSynthesis->submitted_at?->toIso8601String(),
                'categories' => [
                    'convergence' => ['ai_content' => $masterSynthesis->ai_convergence_text ?? '', 'manual_content' => $masterSynthesis->convergence_text ?? ''],
                    'matters_for_consideration' => ['ai_content' => $masterSynthesis->ai_consideration_text ?? '', 'manual_content' => $masterSynthesis->consideration_text ?? ''],
                    'proposals' => ['ai_content' => $masterSynthesis->ai_proposals_text ?? '', 'manual_content' => $masterSynthesis->proposals_text ?? ''],
                    'others' => ['ai_content' => $masterSynthesis->ai_other_text ?? '', 'manual_content' => $masterSynthesis->other_text ?? ''],
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
        $individualSyntheses = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', false)
            ->with('synthesis')
            ->orderBy('submission_number')
            ->get();

        // Fetch master synthesis (if exists)
        $masterSynthesis = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('is_master_synthesis', true)
            ->first();

        $fields = [
            'convergence' => ['ai' => 'ai_convergence_text', 'manual' => 'convergence_text'],
            'matters_for_consideration' => ['ai' => 'ai_consideration_text', 'manual' => 'consideration_text'],
            'proposals' => ['ai' => 'ai_proposals_text', 'manual' => 'proposals_text'],
            'others' => ['ai' => 'ai_other_text', 'manual' => 'other_text'],
        ];

        $categories = [];
        foreach ($fields as $catKey => $catFields) {
            $categories[$catKey] = [
                'individual_syntheses' => $individualSyntheses->map(fn($s) => [
                    'id' => $s->id,
                    'user_id' => $s->synthesis_id,
                    'user_name' => $s->synthesis?->name,
                    'submission_number' => $s->submission_number,
                    'ai_content' => $s->{$catFields['ai']} ?? '',
                    'manual_content' => $s->{$catFields['manual']} ?? '',
                    'is_llm_assisted' => $s->is_llm_assisted,
                    'status' => $s->status,
                    'submitted_at' => $s->submitted_at?->toIso8601String(),
                ])->toArray(),
                'master_synthesis' => $masterSynthesis ? [
                    'id' => $masterSynthesis->id,
                    'user_id' => $masterSynthesis->synthesis_id,
                    'ai_content' => $masterSynthesis->{$catFields['ai']} ?? '',
                    'manual_content' => $masterSynthesis->{$catFields['manual']} ?? '',
                    'is_llm_assisted' => $masterSynthesis->is_llm_assisted,
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
        // Only match records that are master synthesis (is_llm_assisted = false) to avoid
        // accidentally updating a regular user's submitted report.
        $report = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('is_llm_assisted', false)
            ->whereIn('status', ['submitted', 'reviewed'])
            ->first();

        if (!$report) {
            $report = SynthesisReport::create([
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
                'submission_number' => SynthesisReport::getNextSubmissionNumber($conversation->id, $user->id),
                'is_llm_assisted' => false,
                'status' => 'submitted',
            ]);
        }

        $report->update($validated);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return back()->with('success', 'Master synthesis updated successfully.');
    }

    /**
     * Extract JSON from LLM response.
     */
    private function extractJsonFromLlmResponse(string $content): ?array
    {
        $stripped = preg_replace('/^```(?:json)?\s*/', '', $content);
        $stripped = preg_replace('/\s*`$/', '', $stripped);

        $decoded = json_decode($stripped, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        $firstBrace = strpos($stripped, '{');
        $lastBrace = strrpos($stripped, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonString = substr($stripped, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($jsonString, true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($content, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * Normalize text values from LLM response.
     */
    private function normalizeLlmText(string $text): string
    {
        $text = preg_replace('/\*\*\*/', '', $text);
        $text = preg_replace('/\*\*/', '', $text);
        $text = preg_replace('/\*/', '', $text);
        $text = preg_replace('/___/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        return trim($text);
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
        $report = SynthesisReport::where('conversation_id', $conversation->id)
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
}
