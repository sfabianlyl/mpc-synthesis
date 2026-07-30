<?php

namespace App\Jobs;

use App\Models\MasterSynthesis;
use App\Models\SynthesisDraft;
use App\Models\SynthesisSummary;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LlmSynthesisSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $conversationId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $conversationId)
    {
        $this->conversationId = $conversationId;
    }

    /**
     * Execute the job.
     */
    public function handle(LlmService $llm): void
    {
        // Find all SynthesisDraft records associated with this conversation
        // via synthesis_group_assignments (all users assigned to this conversation)
        $drafts = SynthesisDraft::where('conversation_id', $this->conversationId)
            ->where('is_submitted', true)
            ->get();

        if ($drafts->isEmpty()) {
            Log::warning('LlmSynthesisSummaryJob: No submitted drafts found for conversation.', [
                'conversation_id' => $this->conversationId,
            ]);

            // Create a failed summary record
            SynthesisSummary::updateOrCreate(
                ['conversation_id' => $this->conversationId],
                [
                    'convergence_summary' => null,
                    'consideration_summary' => null,
                    'proposals_summary' => null,
                    'other_summary' => null,
                    'provider' => null,
                    'is_successful' => false,
                    'generated_at' => now(),
                ]
            );

            return;
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
        $systemPrompt = <<<'PROMPT'
You are a senior synthesis analyst. You will receive multiple submitted synthesis drafts from different participants in a consultative process.

Your task is to generate a concise summary that captures the key themes, points of agreement, matters for consideration, and proposals across all submitted drafts.

Return your response as a JSON object with exactly these four keys:
{
  "convergence_summary": "Summary of convergences/agreements across all drafts",
  "consideration_summary": "Summary of matters for consideration/concerns across all drafts",
  "proposals_summary": "Summary of proposals/recommendations across all drafts",
  "other_summary": "Summary of any other themes or topics across all drafts"
}

Be concise but thorough. Preserve the original meaning and intent of all participants. Highlight areas of consensus while acknowledging differences.
PROMPT;

        // Call LLM to generate summary
        $result = $llm->query($systemPrompt, $draftContent);

        \Log::info('[DEBUG] LlmSynthesisSummaryJob: LLM result', [
            'success' => $result['success'] ?? false,
            'provider' => $result['provider'] ?? null,
            'content' => $result['content'] ?? null,
            'conversation_id' => $this->conversationId,
        ]);

        if (!$result['success']) {
            Log::error('LlmSynthesisSummaryJob failed.', [
                'error' => $result['error'],
                'provider' => $result['provider'],
                'conversation_id' => $this->conversationId,
            ]);

            SynthesisSummary::updateOrCreate(
                ['conversation_id' => $this->conversationId],
                [
                    'convergence_summary' => null,
                    'consideration_summary' => null,
                    'proposals_summary' => null,
                    'other_summary' => null,
                    'provider' => $result['provider'],
                    'is_successful' => false,
                    'generated_at' => now(),
                ]
            );

            return;
        }

        // Parse JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        \Log::info('[DEBUG] LlmSynthesisSummaryJob: parsed JSON content', [
            'parsed_content' => $content,
            'conversation_id' => $this->conversationId,
        ]);

        if ($content === null) {
            Log::warning('LlmSynthesisSummaryJob returned non-JSON response.', [
                'content' => $result['content'],
                'conversation_id' => $this->conversationId,
            ]);

            SynthesisSummary::updateOrCreate(
                ['conversation_id' => $this->conversationId],
                [
                    'convergence_summary' => null,
                    'consideration_summary' => null,
                    'proposals_summary' => null,
                    'other_summary' => null,
                    'provider' => $result['provider'],
                    'is_successful' => false,
                    'generated_at' => now(),
                ]
            );

            return;
        }

        // Store successful summary
        $stored = SynthesisSummary::updateOrCreate(
            ['conversation_id' => $this->conversationId],
            [
                'convergence_summary' => $content['convergence_summary'] ?? null,
                'consideration_summary' => $content['consideration_summary'] ?? null,
                'proposals_summary' => $content['proposals_summary'] ?? null,
                'other_summary' => $content['other_summary'] ?? null,
                'provider' => $result['provider'],
                'is_successful' => true,
                'generated_at' => now(),
            ]
        );

        \Log::info('[DEBUG] LlmSynthesisSummaryJob: stored summary', [
            'conversation_id' => $this->conversationId,
            'stored_summary' => [
                'id' => $stored->id,
                'convergence_summary' => $stored->convergence_summary,
                'consideration_summary' => $stored->consideration_summary,
                'proposals_summary' => $stored->proposals_summary,
                'other_summary' => $stored->other_summary,
                'is_successful' => $stored->is_successful,
            ],
        ]);
    }

    /**
     * Extract JSON from LLM response content.
     */
    private function extractJsonFromLlmResponse(string $content): ?array
    {
        // Strip markdown code blocks if present
        $stripped = preg_replace('/^```(?:json)?\s*/', '', $content);
        $stripped = preg_replace('/\s*`$/', '', $stripped);

        // Try to decode as JSON directly first
        $decoded = json_decode($stripped, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Handle double-encoded JSON: the LLM may return a JSON string that is itself
        // a JSON object with escaped quotes (e.g., "{\"convergence_summary\": \"...\"}").
        // Try decoding the raw content to see if it's a single JSON string.
        $decoded = json_decode($content, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Handle double-encoded: try decoding the stripped content as a JSON string
        // that contains another JSON object. First decode gives us the inner JSON string,
        // second decode gives us the array.
        if (str_starts_with($stripped, '"') && str_ends_with($stripped, '"')) {
            $inner = json_decode($stripped, true);
            if ($inner !== null && is_string($inner)) {
                $decoded = json_decode($inner, true);
                if ($decoded !== null && is_array($decoded)) {
                    return $decoded;
                }
            }
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

            // Also try decoding the original content for double-encoded case
            $decoded = json_decode($content, true);
            if ($decoded !== null && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
