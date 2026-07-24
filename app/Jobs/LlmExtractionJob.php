<?php

namespace App\Jobs;

use App\Models\ConsensusNote;
use App\Models\Extraction;
use App\Models\Response;
use App\Models\SynthesisGroupAssignment;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LlmExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $conversationId;

    private int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $conversationId, int $userId)
    {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(LlmService $llm): void
    {
        // 1. Get all group consensuses for this user's assigned groups
        $assignedGroupIds = SynthesisGroupAssignment::where('conversation_id', $this->conversationId)
            ->where('user_id', $this->userId)
            ->pluck('group_id');

        // Fetch consensus texts from submitted responses and consensus notes
        $responseConsensusTexts = Response::whereIn('group_id', $assignedGroupIds)
            ->where('conversation_id', $this->conversationId)
            ->whereNotNull('submitted_at')
            ->pluck('consensus_text')
            ->filter()
            ->implode("\n\n---\n\n");

        $noteConsensusTexts = ConsensusNote::whereIn('group_id', $assignedGroupIds)
            ->where('conversation_id', $this->conversationId)
            ->where('user_id', $this->userId)
            ->whereNotNull('text')
            ->pluck('text')
            ->filter()
            ->implode("\n\n---\n\n");

        $consensusTexts = trim($responseConsensusTexts . ($noteConsensusTexts ? "\n\n---\n\n" . $noteConsensusTexts : ''));

        if (empty($consensusTexts)) {
            // No consensuses available
            Extraction::updateOrCreate(
                ['conversation_id' => $this->conversationId, 'user_id' => $this->userId],
                [
                    'convergence_text' => null,
                    'consideration_text' => null,
                    'proposals_text' => null,
                    'provider' => null,
                    'is_successful' => false,
                    'extracted_at' => now(),
                ]
            );
            return;
        }

        // 2. Call LLM with synthesis prompt
        $result = $llm->query(
            $llm->buildSynthesisPrompt(),
            $consensusTexts
        );

        if (!$result['success']) {
            Log::error('LlmExtractionJob failed.', ['error' => $result['error'], 'provider' => $result['provider']]);
            Extraction::updateOrCreate(
                ['conversation_id' => $this->conversationId, 'user_id' => $this->userId],
                [
                    'convergence_text' => null,
                    'consideration_text' => null,
                    'proposals_text' => null,
                    'provider' => $result['provider'],
                    'is_successful' => false,
                    'extracted_at' => now(),
                ]
            );
            return;
        }

        // 3. Parse JSON response
        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            Log::warning('LlmExtractionJob returned non-JSON response.', [
                'content' => $result['content'],
            ]);
            Extraction::updateOrCreate(
                ['conversation_id' => $this->conversationId, 'user_id' => $this->userId],
                [
                    'convergence_text' => null,
                    'consideration_text' => null,
                    'proposals_text' => null,
                    'provider' => $result['provider'],
                    'is_successful' => false,
                    'extracted_at' => now(),
                ]
            );
            return;
        }

        // 4. Store successful extraction
        Extraction::updateOrCreate(
            ['conversation_id' => $this->conversationId, 'user_id' => $this->userId],
            [
                'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? '',
                'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? '',
                'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? '',
                'provider' => $result['provider'],
                'is_successful' => true,
                'extracted_at' => now(),
            ]
        );
    }

    /**
     * Extract JSON from LLM response content.
     */
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
