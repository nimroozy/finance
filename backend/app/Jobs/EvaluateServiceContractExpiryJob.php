<?php

namespace App\Jobs;

use App\Models\Services\Service;
use App\Models\Services\ServiceContract;
use App\Services\AuditLogger;
use App\Services\ServiceLifecycle\ServiceLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateServiceContractExpiryJob implements ShouldQueue
{
    use Queueable;

    public function handle(ServiceLifecycleService $lifecycle, AuditLogger $audit): void
    {
        ServiceContract::query()
            ->where('status', ServiceContract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (ServiceContract $contract) use ($audit) {
                $contract->status = ServiceContract::STATUS_EXPIRED;
                $contract->save();
                $audit->log('service_contract.expired', $contract, null, ['end_date' => $contract->end_date]);
            });

        Service::query()
            ->where('commercial_status', Service::COMMERCIAL_ACTIVE)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', now()->toDateString())
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (Service $service) use ($lifecycle) {
                try {
                    $lifecycle->transition($service, [
                        'commercial' => Service::COMMERCIAL_EXPIRED,
                    ], null, 'contract_expiry_job', 'job');
                } catch (\Throwable) {
                    // skip invalid transitions
                }
            });
    }
}
