<?php

namespace App\Broadcasting\Events;

use App\Models\SynthesisReport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MasterSynthesisUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public SynthesisReport $report
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("conversation.{$this->conversationId}"),
        ];
    }

    /**
     * The event's broadcast payload.
     */
    public function broadcastWith(): array
    {
        return [
            'masterSynthesis' => [
                'id' => $this->report->id,
                'convergence_text' => $this->report->convergence_text,
                'consideration_text' => $this->report->consideration_text,
                'proposals_text' => $this->report->proposals_text,
                'other_text' => $this->report->other_text,
                'updated_at' => $this->report->updated_at?->toIso8601String(),
            ],
        ];
    }
}
