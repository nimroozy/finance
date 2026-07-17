<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceFinanceHoldPlaced;
use App\Models\Services\Service;
use App\Models\Services\ServiceFinanceHold;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceFinanceHoldService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function place(Service $service, array $data, ?User $actor = null): ServiceFinanceHold
    {
        if (empty($data['reason'])) {
            throw new InvalidArgumentException('Finance hold requires a reason.');
        }

        return DB::transaction(function () use ($service, $data, $actor) {
            $hold = ServiceFinanceHold::query()->create([
                'service_id' => $service->id,
                'hold_type' => $data['hold_type'] ?? 'overdue',
                'reason' => $data['reason'],
                'zoho_invoice_ref' => $data['zoho_invoice_ref'] ?? null,
                'outstanding_snapshot' => $data['outstanding_snapshot'] ?? null,
                'effective_at' => $data['effective_at'] ?? now(),
                'review_at' => $data['review_at'] ?? null,
                'approved_by' => $actor?->id,
                'status' => ServiceFinanceHold::STATUS_ACTIVE,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->lifecycle->transition($service, [
                'billing' => Service::BILLING_ON_HOLD,
            ], $actor, $data['reason'], 'finance_hold');

            $this->audit->log('service.finance_hold_placed', $service, null, $hold->toArray(), $service->branch_id);
            ServiceFinanceHoldPlaced::dispatch($service->id, (int) $service->branch_id);

            return $hold;
        });
    }

    public function release(ServiceFinanceHold $hold, ?User $actor = null): ServiceFinanceHold
    {
        return DB::transaction(function () use ($hold, $actor) {
            $hold->status = ServiceFinanceHold::STATUS_RELEASED;
            $hold->released_by = $actor?->id;
            $hold->save();

            $service = Service::query()->findOrFail($hold->service_id);
            $activeHolds = ServiceFinanceHold::query()
                ->where('service_id', $service->id)
                ->where('status', ServiceFinanceHold::STATUS_ACTIVE)
                ->count();

            if ($activeHolds === 0 && $service->billing_status === Service::BILLING_ON_HOLD) {
                $this->lifecycle->transition($service, [
                    'billing' => Service::BILLING_CURRENT,
                ], $actor, 'finance_hold_released', 'finance_hold');
            }

            $this->audit->log('service.finance_hold_released', $service, null, $hold->toArray(), $service->branch_id);

            return $hold->fresh();
        });
    }
}
