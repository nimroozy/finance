<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceReactivated;
use App\Models\Services\Service;
use App\Models\Services\ServiceSuspension;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceReactivationService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function reactivate(Service $service, array $data = [], ?User $actor = null): Service
    {
        if ($service->commercial_status !== Service::COMMERCIAL_SUSPENDED) {
            throw new InvalidArgumentException('Only suspended services can be reactivated.');
        }

        return DB::transaction(function () use ($service, $data, $actor) {
            $this->lifecycle->transition($service, [
                'commercial' => Service::COMMERCIAL_ACTIVE,
                'operational' => Service::OPERATIONAL_ONLINE,
                'billing' => Service::BILLING_CURRENT,
            ], $actor, $data['reason'] ?? 'reactivation', 'reactivation', $data['notes'] ?? null);

            $service->refresh();
            $service->reactivation_date = now();
            $service->suspension_date = null;
            $service->updated_by = $actor?->id;
            $service->save();

            ServiceSuspension::query()
                ->where('service_id', $service->id)
                ->whereNull('reactivated_at')
                ->orderByDesc('id')
                ->limit(1)
                ->update(['reactivated_at' => now(), 'notification_status' => 'sent']);

            $this->audit->log('service.reactivated', $service, null, null, $service->branch_id);
            ServiceReactivated::dispatch($service->id, (int) $service->branch_id);

            return $service->fresh();
        });
    }
}
