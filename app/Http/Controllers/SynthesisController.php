<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Response;
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

        $report = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->first();

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

        // Create or update the synthesis report
        $report = SynthesisReport::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'synthesis_id' => $user->id,
            ],
            [
                'convergence_text' => $parsed['convergence'] ?? $parsed['convergence_text'] ?? '',
                'consideration_text' => $parsed['consideration'] ?? $parsed['consideration_text'] ?? '',
                'proposals_text' => $parsed['proposals'] ?? $parsed['proposals_text'] ?? '',
                'llm_generated' => true,
                'status' => 'draft',
            ]
        );

        // Calculate group count for feedback
        $groupCount = $selectedGroupIds ? count($selectedGroupIds) : $responses->count();

        Log::info('LLM analysis: report created/updated.', [
            'report_id' => $report->id,
            'convergence_text_length' => strlen($report->convergence_text),
            'consideration_text_length' => strlen($report->consideration_text),
            'proposals_text_length' => strlen($report->proposals_text),
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

        // Re-render the review page with complete data (same as review() method)
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
        ])
            ->with('success', 'LLM analysis completed successfully for '.$groupCount.' group(s) using '.$result['provider'].' provider.');
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
}
