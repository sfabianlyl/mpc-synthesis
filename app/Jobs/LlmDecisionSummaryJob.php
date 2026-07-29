<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\DecisionDraft;
use App\Models\DecisionSummary;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LlmDecisionSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $query = DecisionDraft::where('conversation_id', $this->conversationId)
            ->where('is_submitted', true);

        if ($this->level === 'diocese' && $this->dioceseId !== null) {
            $query->where('diocese_id', $this->dioceseId);
        }

        $drafts = $query->get();

        if ($drafts->isEmpty()) {
            Log::warning('LlmDecisionSummaryJob: No submitted decision drafts found.', [
                'conversation_id' => $this->conversationId,
                'diocese_id' => $this->dioceseId,
                'level' => $this->level,
            ]);

            DecisionSummary::updateOrCreate(
                [
                    'conversation_id' => $this->conversationId,
                    'level' => $this->level,
                    'diocese_id' => $this->dioceseId,
                ],
                [
                    'summary_text' => null,
                    'is_successful' => false,
                    'provider' => null,
                    'generated_at' => now(),
                ]
            );

            return;
        }

        // Gather submitted draft texts
        $column = ($this->level === 'national') ? 'national_text' : 'diocesan_text';
        $texts = $drafts->pluck($column)->filter()->implode("\n\n---\n\n");

        if (empty($texts)) {
            DecisionSummary::updateOrCreate(
                [
                    'conversation_id' => $this->conversationId,
                    'level' => $this->level,
                    'diocese_id' => $this->dioceseId,
                ],
                [
                    'summary_text' => null,
                    'is_successful' => false,
                    'provider' => null,
                    'generated_at' => now(),
                ]
            );
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

            DecisionSummary::updateOrCreate(
                [
                    'conversation_id' => $this->conversationId,
                    'level' => $this->level,
                    'diocese_id' => $this->dioceseId,
                ],
                [
                    'summary_text' => null,
                    'is_successful' => false,
                    'provider' => $result['provider'] ?? null,
                    'generated_at' => now(),
                ]
            );

            return;
        }

        DecisionSummary::updateOrCreate(
            [
                'conversation_id' => $this->conversationId,
                'level' => $this->level,
                'diocese_id' => $this->dioceseId,
            ],
            [
                'summary_text' => $result['content'],
                'is_successful' => true,
                'provider' => $result['provider'] ?? null,
                'generated_at' => now(),
            ]
        );
    }
}
