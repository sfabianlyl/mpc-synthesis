<?php

namespace App\Broadcasting\Events;

use App\Models\SynthesisReport;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SynthesisSubmitted implements ShouldBroadcastNow
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
            'submission' => [
                'id' => $this->report->id,
                'submission_number' => $this->report->submission_number,
                'user' => [
                    'id' => $this->report->synthesis->id,
                    'name' => $this->report->synthesis->name,
                    'email' => $this->report->synthesis->email,
                ],
                'status' => 'submitted',
                'submitted_at' => $this->report->submitted_at?->toIso8601String(),
            ],
        ];
    }
}
