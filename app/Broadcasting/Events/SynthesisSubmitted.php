<?php

namespace App\Broadcasting\Events;

use App\Models\MasterSynthesis;
use Illuminate\Foundation\Events\Dispatchable;

class SynthesisSubmitted
{
    use Dispatchable;

    public function __construct(
        public int $conversationId,
        public MasterSynthesis $report
    ) {}
}
