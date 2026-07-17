<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceCancelled;
use App\Models\Inventory\CustomerEquipment;
use App\Models\Services\Service;
use App\Models\Services\ServiceCancellation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceCancellationService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(Service $service, array $data, ?User $actor = null): ServiceCancellation
    {
        if (empty($data['reason'])) {
            throw new InvalidArgumentException('Cancellation requires a reason.');
        }

        if (in_array($service->commercial_status, [Service::COMMERCIAL_CANCELLED], true)) {
            throw new InvalidArgumentException('Service is already cancelled.');
        }

        return DB::transaction(function () use ($service, $data, $actor) {
            $equipmentReturn = (bool) ($data['equipment_return_required'] ?? true);

            if ($service->commercial_status === Service::COMMERCIAL_ACTIVE
                || $service->commercial_status === Service::COMMERCIAL_SUSPENDED) {
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_PENDING_CANCELLATION,
                ], $actor, $data['reason'], 'cancellation');
            }

            $cancellation = ServiceCancellation::query()->create([
                'service_id' => $service->id,
                'reason' => $data['reason'],
                'requested_by' => $actor?->id,
                'equipment_return_required' => $equipmentReturn,
                'status' => ServiceCancellation::STATUS_REQUESTED,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('service.cancellation_requested', $service, null, $cancellation->toArray(), $service->branch_id);

            return $cancellation;
        });
    }

    public function complete(ServiceCancellation $cancellation, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($cancellation, $actor) {
            $service = Service::query()->lockForUpdate()->findOrFail($cancellation->service_id);

            if ($cancellation->equipment_return_required) {
                $open = CustomerEquipment::withoutGlobalScopes()
                    ->where('service_id', $service->id)
                    ->whereNull('returned_at')
                    ->where('status', '!=', 'returned')
                    ->count();
                // Soft requirement: flag in notes when equipment still out; do not invent ledger moves.
                if ($open > 0 && empty($cancellation->notes)) {
                    $cancellation->notes = "Equipment return pending ({$open} item(s)).";
                }
            }

            if ($service->commercial_status === Service::COMMERCIAL_PENDING_CANCELLATION
                || $service->canTransitionCommercialTo(Service::COMMERCIAL_CANCELLED)) {
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_CANCELLED,
                    'operational' => Service::OPERATIONAL_DECOMMISSIONED,
                    'billing' => Service::BILLING_CLOSED,
                ], $actor, $cancellation->reason, 'cancellation');
            } else {
                // draft/pending_activation cancel path
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_CANCELLED,
                ], $actor, $cancellation->reason, 'cancellation');
                if ($service->fresh()->canTransitionOperationalTo(Service::OPERATIONAL_DECOMMISSIONED)) {
                    $this->lifecycle->transition($service->fresh(), [
                        'operational' => Service::OPERATIONAL_DECOMMISSIONED,
                    ], $actor, $cancellation->reason, 'cancellation');
                }
            }

            $service->refresh();
            $service->cancellation_date = now();
            $service->updated_by = $actor?->id;
            $service->save();

            $cancellation->status = ServiceCancellation::STATUS_COMPLETED;
            $cancellation->approved_by = $actor?->id;
            $cancellation->cancelled_at = now();
            $cancellation->save();

            $this->audit->log('service.cancelled', $service, null, $cancellation->toArray(), $service->branch_id);
            ServiceCancelled::dispatch($service->id, (int) $service->branch_id, $cancellation->reason);

            return $service->fresh();
        });
    }
}
