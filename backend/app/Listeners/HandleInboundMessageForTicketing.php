<?php

namespace App\Listeners;

use App\Events\InboundMessageReceived;
use App\Services\Tickets\TicketIntakeService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class HandleInboundMessageForTicketing implements ShouldQueueAfterCommit
{
    public function __construct(private TicketIntakeService $intake) {}

    public function handle(InboundMessageReceived $event): void
    {
        if (! config('ticketing.enabled', true)) {
            return;
        }

        $this->intake->handleInboundMessage($event->inboundMessageId);
    }
}
