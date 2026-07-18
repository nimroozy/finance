<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboundMessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $inboundMessageId, public ?int $conversationId,
    ) {}
}
