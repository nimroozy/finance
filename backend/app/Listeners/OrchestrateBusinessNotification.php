<?php

namespace App\Listeners;

use App\Events\BusinessNotificationRequested;
use App\Services\Notifications\NotificationOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class OrchestrateBusinessNotification implements ShouldQueueAfterCommit
{
    public function __construct(private NotificationOrchestrator $orchestrator) {}

    public function handle(BusinessNotificationRequested $event): void
    {
        $this->orchestrator->createIntent($event->eventType, $event->payload, $event->channels);
    }
}
