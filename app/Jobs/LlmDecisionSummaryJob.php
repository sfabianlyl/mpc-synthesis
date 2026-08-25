<?php

namespace App\Jobs;

use App\Models\DioceseDecisionPart;
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
    private ?int $dioceseId;
    private string $level; // 'national' or 'diocese'

    /**
     * Create a new job instance.
     *
     * @param int $conversationId
     * @param int|null $dioceseId
     * @param string $level 'national' or 'diocese'
     */
    public function __construct(int $conversationId, ?int $dioceseId, string $level)
    {
        $this->conversationId = $conversationId;
        $this->dioceseId = $dioceseId;
        $this->level = $level;
    }

    /**
     * Execute the job.
     */
    public function handle(LlmService $llm): void
    {
        // Gather submitted decision parts for the given conversation level
        if ($this->level === 'diocese') {
            $parts = DioceseDecisionPart::where('conversation_id', $this->conversationId)
                ->where('is_submitted', true)
                ->get();
        } else {
            $parts = NationalDecisionPart::where('conversation_id', $this->conversationId)
                ->where('is_submitted', true)
                ->get();
        }

        if ($parts->isEmpty()) {
            Log::warning('LlmDecisionSummaryJob: No submitted decision parts found.', [
                'conversation_id' => $this->conversationId,
                'diocese_id' => $this->dioceseId,
                'level' => $this->level,
            ]);

            return;
        }

        // Gather submitted part texts
        if ($this->level === 'national') {
            $texts = $parts->pluck('national_text')->filter()->implode("\n\n---\n\n");
        } else {
            $texts = $parts->pluck('diocesan_text')->filter()->implode("\n\n---\n\n");
        }

        if (empty($texts)) {
            return;
        }

        $systemPrompt = "You are an AI assistant summarizing pastoral decision inputs at the {$this->level} level. Produce a clear, cohesive, and concise summary of the key themes, agreements, and proposals.";
        $userPrompt = "Summarize the following pastoral submissions:\n\n" . $texts;

        $result = $llm->query($systemPrompt, $userPrompt);

        if (!$result['success']) {
            Log::error('LlmDecisionSummaryJob failed.', [
                'error' => $result['error'],
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        // Update the appropriate decision part with the summary
        if ($this->level === 'national') {
            NationalDecisionPart::where('conversation_id', $this->conversationId)
                ->update(['national_text' => $result['content']]);
        } else {
            DioceseDecisionPart::where('conversation_id', $this->conversationId)
                ->update(['diocesan_text' => $result['content']]);
        }
    }
}
