<?php

namespace App\Jobs;

use App\Models\ConsensusNote;
use App\Models\SynthesisDraft;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LlmSynthesisDraftJob implements ShouldQueue
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
     * Execute the job to summarize all user ConsensusNotes into SynthesisDraft fields.
     */
    public function handle(LlmService $llm): void
    {
        // Fetch all non-empty consensus notes created by this synthesis member for this conversation
        $notes = ConsensusNote::where('conversation_id', $this->conversationId)
            ->where('user_id', $this->userId)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->with('group')
            ->get();

        if ($notes->isEmpty()) {
            Log::info('LlmSynthesisDraftJob: No consensus notes found for user.', [
                'conversation_id' => $this->conversationId,
                'user_id' => $this->userId,
            ]);
            return;
        }

        $formattedNotes = $notes->map(function ($note) {
            $groupName = $note->group ? $note->group->name : 'Group ' . $note->group_id;
            return sprintf("=== Note for %s ===\n%s", $groupName, $note->text);
        })->implode("\n\n---\n\n");

        // Query LLM using standard synthesis prompt
        $result = $llm->query(
            $llm->buildNotesSynthesisPrompt(),
            $formattedNotes
        );

        if (!$result['success']) {
            Log::error('LlmSynthesisDraftJob failed.', [
                'error' => $result['error'],
                'provider' => $result['provider'],
            ]);
            return;
        }

        $content = $this->extractJsonFromLlmResponse($result['content']);

        if ($content === null) {
            Log::warning('LlmSynthesisDraftJob returned invalid JSON.', [
                'content' => $result['content'],
            ]);
            return;
        }

        // Store/update synthesis draft with LLM summarized content from notes
        SynthesisDraft::updateOrCreate(
            ['conversation_id' => $this->conversationId, 'user_id' => $this->userId],
            [
                'convergence_text' => $content['convergence'] ?? $content['convergence_text'] ?? '',
                'consideration_text' => $content['consideration'] ?? $content['consideration_text'] ?? '',
                'proposals_text' => $content['proposals'] ?? $content['proposals_text'] ?? '',
                'other_text' => $content['others'] ?? $content['other_text'] ?? '',
            ]
        );
    }

    /**
     * Extract JSON from LLM response content.
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

        return null;
    }
}
