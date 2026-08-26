<?php

namespace App\Jobs;

use App\Models\NationalDecisionPart;
use App\Services\LlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LlmDecisionSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    private int $conversationId;

    /**
     * Create a new job instance.
     *
     * @param int $conversationId
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
        // Gather submitted national decision parts for the given conversation
        $parts = NationalDecisionPart::where('conversation_id', $this->conversationId)
            ->where('is_submitted', true)
            ->get();

        if ($parts->isEmpty()) {
            Log::warning('LlmDecisionSummaryJob: No submitted decision parts found.', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        // Gather submitted part texts
        $texts = $parts->pluck('national_text')->filter()->implode("\n\n---\n\n");

        if (empty($texts)) {
            return;
        }

        $systemPrompt = "You are an AI assistant summarizing pastoral decision inputs. Produce a clear, cohesive, and concise summary of the key themes, agreements, and proposals.";
        $userPrompt = "Summarize the following pastoral submissions:\n\n" . $texts;

        $result = $llm->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            Log::error('LlmDecisionSummaryJob failed.', [
                'error' => $result['error'],
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        // Update the national decision part with the summary
        NationalDecisionPart::where('conversation_id', $this->conversationId)
            ->update(['national_text' => $result['content']]);
    }
}
