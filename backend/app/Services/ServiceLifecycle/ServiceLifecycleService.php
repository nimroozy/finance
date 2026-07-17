<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceStatusTransition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceLifecycleService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{commercial?: string, operational?: string, billing?: string}  $to
     */
    public function transition(
        Service $service,
        array $to,
        ?User $actor = null,
        ?string $reason = null,
        string $source = 'api',
        ?string $comment = null,
    ): Service {
        return DB::transaction(function () use ($service, $to, $actor, $reason, $source, $comment) {
            /** @var Service $locked */
            $locked = Service::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();

            $fromCommercial = $locked->commercial_status;
            $fromOperational = $locked->operational_status;
            $fromBilling = $locked->billing_status;

            if (isset($to['commercial'])) {
                if (! $locked->canTransitionCommercialTo($to['commercial'])) {
                    throw new InvalidArgumentException(
                        "Invalid commercial transition [{$fromCommercial} → {$to['commercial']}]."
                    );
                }
                $locked->commercial_status = $to['commercial'];
            }

            if (isset($to['operational'])) {
                if (! $locked->canTransitionOperationalTo($to['operational'])) {
                    throw new InvalidArgumentException(
                        "Invalid operational transition [{$fromOperational} → {$to['operational']}]."
                    );
                }
                $locked->operational_status = $to['operational'];
            }

            if (isset($to['billing'])) {
                if (! in_array($to['billing'], Service::BILLING_STATUSES, true)) {
                    throw new InvalidArgumentException("Invalid billing status [{$to['billing']}].");
                }
                $locked->billing_status = $to['billing'];
            }

            $locked->updated_by = $actor?->id;
            $locked->save();

            ServiceStatusTransition::query()->create([
                'service_id' => $locked->id,
                'from_commercial' => $fromCommercial,
                'to_commercial' => $locked->commercial_status,
                'from_operational' => $fromOperational,
                'to_operational' => $locked->operational_status,
                'from_billing' => $fromBilling,
                'to_billing' => $locked->billing_status,
                'user_id' => $actor?->id,
                'reason' => $reason,
                'source' => $source,
                'comment' => $comment,
                'created_at' => now(),
            ]);

            $this->audit->log('service.status_transition', $locked, [
                'commercial' => $fromCommercial,
                'operational' => $fromOperational,
                'billing' => $fromBilling,
            ], [
                'commercial' => $locked->commercial_status,
                'operational' => $locked->operational_status,
                'billing' => $locked->billing_status,
            ], $locked->branch_id, $reason);

            return $locked->fresh();
        });
    }
}
