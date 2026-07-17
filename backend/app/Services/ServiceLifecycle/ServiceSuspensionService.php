<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceSuspended;
use App\Models\Services\Service;
use App\Models\Services\ServiceSuspension;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceSuspensionService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function suspend(Service $service, array $data, ?User $actor = null): Service
    {
        if ($service->commercial_status !== Service::COMMERCIAL_ACTIVE) {
            throw new InvalidArgumentException('Only active services can be suspended.');
        }
        if (empty($data['reason'])) {
            throw new InvalidArgumentException('Suspension requires a reason.');
        }

        return DB::transaction(function () use ($service, $data, $actor) {
            $this->lifecycle->transition($service, [
                'commercial' => Service::COMMERCIAL_SUSPENDED,
                'operational' => Service::OPERATIONAL_SUSPENDED,
                'billing' => ($data['type'] ?? '') === ServiceSuspension::TYPE_FINANCE
                    ? Service::BILLING_ON_HOLD
                    : $service->billing_status,
            ], $actor, $data['reason'], 'suspension', $data['notes'] ?? null);

            $service->refresh();
            $service->suspension_date = now();
            $service->updated_by = $actor?->id;
            $service->save();

            ServiceSuspension::query()->create([
                'service_id' => $service->id,
                'type' => $data['type'] ?? ServiceSuspension::TYPE_VOLUNTARY,
                'reason' => $data['reason'],
                'effective_at' => $data['effective_at'] ?? now(),
                'expected_reactivation_at' => $data['expected_reactivation_at'] ?? null,
                'approved_by' => $data['approved_by'] ?? $actor?->id,
                'requested_by' => $data['requested_by'] ?? $actor?->id,
                'finance_reference' => $data['finance_reference'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'notification_status' => 'pending',
            ]);

            $this->audit->log('service.suspended', $service, null, ['reason' => $data['reason']], $service->branch_id);
            ServiceSuspended::dispatch($service->id, (int) $service->branch_id, (string) $data['reason']);

            return $service->fresh();
        });
    }
}
