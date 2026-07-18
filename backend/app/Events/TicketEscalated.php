<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketEscalated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $ticketId, public int $branchId, public ?int $escalationId,
    ) {}
}
