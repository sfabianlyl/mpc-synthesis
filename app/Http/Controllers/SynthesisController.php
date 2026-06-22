<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\SynthesisReport;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SynthesisController extends Controller
{
    /**
     * Show the synthesis team dashboard with assigned groups and their responses for the selected session.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all conversations (sessions) to populate select dropdown
        $conversations = Conversation::orderBy('created_at', 'desc')->get(['id', 'title', 'status']);
        
        if ($conversations->isEmpty()) {
            return Inertia::render('synthesis/dashboard', [
                'conversations' => [],
                'groups' => [],
                'report' => null,
                'selectedConversationId' => null,
            ]);
        }

        // Determine which session is selected
        $selectedConversationId = $request->input('conversation_id');
        if (!$selectedConversationId) {
            $activeConv = $conversations->where('status', 'active')->first();
            $selectedConversationId = $activeConv ? $activeConv->id : $conversations->first()->id;
        }
        $selectedConversationId = (int) $selectedConversationId;

        // Fetch groups assigned to this synthesis member
        $groups = Group::where('synthesis_id', $user->id)
            ->with(['responses' => function ($query) use ($selectedConversationId) {
                $query->where('conversation_id', $selectedConversationId);
            }])
            ->get();

        // Fetch the synthesis report for this session and synthesis member
        $report = SynthesisReport::where('conversation_id', $selectedConversationId)
            ->where('synthesis_id', $user->id)
            ->first();

        return Inertia::render('synthesis/dashboard', [
            'conversations' => $conversations,
            'groups' => $groups,
            'report' => $report,
            'selectedConversationId' => $selectedConversationId,
        ]);
    }

    /**
     * Show the review/edit page for a specific session's synthesis report.
     */
    public function review(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Fetch the 5 groups assigned to this synthesis member, and their responses for this session
        $groups = Group::where('synthesis_id', $user->id)
            ->with(['responses' => function ($query) use ($conversation) {
                $query->where('conversation_id', $conversation->id);
            }, 'responses.facilitator'])
            ->get();

        $report = SynthesisReport::where('conversation_id', $conversation->id)
            ->where('synthesis_id', $user->id)
            ->first();

        return Inertia::render('synthesis/review', [
            'conversation' => $conversation,
            'groups' => $groups,
            'responses' => $groups->flatMap->responses,
            'report' => $report,
        ]);
    }

    /**
     * Trigger LLM analysis for all responses of groups assigned to this synthesis member in a session.
     */
    public function analyze(Request $request, Conversation $conversation, LlmService $llm)
    {
        $user = $request->user();

        // Find assigned groups
        $groupIds = Group::where('synthesis_id', $user->id)->pluck('id');

        if ($groupIds->isEmpty()) {
            return back()->withErrors(['analysis' => 'No groups are assigned to you.']);
        }

        // Fetch responses from these groups for this session
        $responses = \App\Models\Response::where('conversation_id', $conversation->id)
            ->whereIn('group_id', $groupIds)
            ->with('group')
            ->get();

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

        return back()->with('success', 'LLM analysis completed successfully using '.$result['provider'].' provider.');
    }

    /**
     * Update a synthesis report (manual edits).
     */
    public function update(Request $request, SynthesisReport $report)
    {
        $user = $request->user();

        if (! $user->isAdmin() && $report->synthesis_id !== $user->id) {
            abort(403, 'You do not own this report.');
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

        if (! $user->isAdmin() && $report->synthesis_id !== $user->id) {
            abort(403, 'You do not own this report.');
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
        $stripped = preg_replace('/\s*```$/', '', $stripped);

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
