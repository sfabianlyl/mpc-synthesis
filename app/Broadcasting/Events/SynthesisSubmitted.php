<?php

namespace App\Broadcasting\Events;

use App\Models\MasterSynthesis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SynthesisSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public MasterSynthesis $report
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->report->id,
            'conversation_id' => $this->report->conversation_id,
            'synthesis_id' => $this->report->synthesis_id,
            'status' => $this->report->status,
            'created_at' => $this->report->created_at?->toISOString(),
        ];
    }
}
