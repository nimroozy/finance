<?php

namespace App\Listeners\Services;

use App\Events\BusinessNotificationRequested;
use App\Events\Services\ServiceActivated;
use App\Events\Services\ServiceCancelled;
use App\Events\Services\ServiceCreated;
use App\Events\Services\ServiceFinanceHoldPlaced;
use App\Events\Services\ServicePackageChanged;
use App\Events\Services\ServiceReactivated;
use App\Events\Services\ServiceRelocated;
use App\Events\Services\ServiceSuspended;
use App\Models\Services\Service;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class EmitBusinessNotificationFromServiceEvents implements ShouldQueueAfterCommit
{
    public function onDomainEvent(object $event): void
    {
        $mapped = $this->map($event);
        if ($mapped === null) {
            return;
        }

        BusinessNotificationRequested::dispatch($mapped['event_type'], $mapped['payload'], $mapped['channels']);
    }

    /**
     * @return array{event_type: string, payload: array, channels: list<string>}|null
     */
    private function map(object $event): ?array
    {
        return match (true) {
            $event instanceof ServiceCreated => $this->payload('service_created', $event->serviceId, $event->branchId, 'Service created'),
            $event instanceof ServiceActivated => $this->payload('service_activated', $event->serviceId, $event->branchId, 'Service activated'),
            $event instanceof ServiceSuspended => $this->payload('service_suspended', $event->serviceId, $event->branchId, 'Service suspended: '.$event->reason),
            $event instanceof ServiceReactivated => $this->payload('service_reactivated', $event->serviceId, $event->branchId, 'Service reactivated'),
            $event instanceof ServiceCancelled => $this->payload('service_cancelled', $event->serviceId, $event->branchId, 'Service cancelled: '.$event->reason),
            $event instanceof ServicePackageChanged => $this->payload('service_package_changed', $event->serviceId, $event->branchId, 'Service package changed'),
            $event instanceof ServiceRelocated => $this->payload('service_relocated', $event->serviceId, $event->branchId, 'Service relocated'),
            $event instanceof ServiceFinanceHoldPlaced => $this->payload('service_finance_hold', $event->serviceId, $event->branchId, 'Service finance hold placed'),
            default => null,
        };
    }

    /**
     * @return array{event_type: string, payload: array, channels: list<string>}
     */
    private function payload(string $eventType, int $serviceId, int $branchId, string $message): array
    {
        $service = Service::withoutGlobalScopes()->find($serviceId);

        return [
            'event_type' => $eventType,
            'payload' => [
                'service_id' => $serviceId,
                'branch_id' => $branchId,
                'service_number' => $service?->service_number,
                'customer_id' => $service?->customer_id,
                'message' => $message,
                // WhatsApp path is mocked via BusinessNotificationRequested + orchestrator; no Radius.
            ],
            'channels' => ['in_app', 'whatsapp'],
        ];
    }
}
