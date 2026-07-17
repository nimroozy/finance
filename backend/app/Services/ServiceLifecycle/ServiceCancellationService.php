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

    public function approve(ServiceCancellation $cancellation, ?User $actor = null): ServiceCancellation
    {
        if ($cancellation->status !== ServiceCancellation::STATUS_REQUESTED) {
            throw new InvalidArgumentException('Only requested cancellations can be approved.');
        }

        $cancellation->status = ServiceCancellation::STATUS_APPROVED;
        $cancellation->approved_by = $actor?->id;
        $cancellation->save();

        if ($cancellation->equipment_return_required) {
            $cancellation->status = ServiceCancellation::STATUS_EQUIPMENT_RECOVERY;
            $cancellation->save();
        }

        $this->audit->log('service.cancellation_approved', $cancellation->service, null, [
            'cancellation_id' => $cancellation->id,
        ], $cancellation->service?->branch_id);

        return $cancellation->fresh();
    }

    /**
     * Record equipment recovery step (does not mutate inventory ledgers).
     */
    public function markEquipmentRecovered(ServiceCancellation $cancellation, ?User $actor = null, ?string $notes = null): ServiceCancellation
    {
        if (! in_array($cancellation->status, [
            ServiceCancellation::STATUS_APPROVED,
            ServiceCancellation::STATUS_EQUIPMENT_RECOVERY,
        ], true)) {
            throw new InvalidArgumentException('Cancellation is not in equipment recovery.');
        }

        $open = CustomerEquipment::withoutGlobalScopes()
            ->where('service_id', $cancellation->service_id)
            ->whereNull('returned_at')
            ->where('status', '!=', 'returned')
            ->count();

        if ($open > 0) {
            // Soft gate: allow marking recovered with note when items remain (field may recover offline).
            $note = $notes ?: "Equipment recovery recorded with {$open} item(s) still flagged open.";
            $cancellation->notes = trim(($cancellation->notes ? $cancellation->notes."\n" : '').$note);
        } elseif ($notes) {
            $cancellation->notes = trim(($cancellation->notes ? $cancellation->notes."\n" : '').$notes);
        }

        $cancellation->equipment_recovered_at = now();
        $cancellation->equipment_recovered_by = $actor?->id;
        $cancellation->status = ServiceCancellation::STATUS_APPROVED;
        $cancellation->save();

        $this->audit->log('service.cancellation_equipment_recovered', $cancellation->service, null, [
            'cancellation_id' => $cancellation->id,
            'open_equipment' => $open,
        ], $cancellation->service?->branch_id);

        return $cancellation->fresh();
    }

    /**
     * Complete cancellation. Must be called explicitly — defaults false at API.
     */
    public function complete(ServiceCancellation $cancellation, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($cancellation, $actor) {
            if ($cancellation->status === ServiceCancellation::STATUS_REQUESTED) {
                throw new InvalidArgumentException('Cancellation must be approved before completion.');
            }

            if ($cancellation->equipment_return_required
                && empty($cancellation->equipment_recovered_at)
                && $cancellation->status === ServiceCancellation::STATUS_EQUIPMENT_RECOVERY) {
                throw new InvalidArgumentException('Equipment recovery step required before completing cancellation.');
            }

            if ($cancellation->equipment_return_required && empty($cancellation->equipment_recovered_at)) {
                throw new InvalidArgumentException('Equipment recovery step required before completing cancellation.');
            }

            $service = Service::query()->lockForUpdate()->findOrFail($cancellation->service_id);

            if ($service->commercial_status === Service::COMMERCIAL_PENDING_CANCELLATION
                || $service->canTransitionCommercialTo(Service::COMMERCIAL_CANCELLED)) {
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_CANCELLED,
                    'operational' => Service::OPERATIONAL_DECOMMISSIONED,
                    'billing' => Service::BILLING_CLOSED,
                ], $actor, $cancellation->reason, 'cancellation');
            } else {
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
            $cancellation->approved_by = $cancellation->approved_by ?? $actor?->id;
            $cancellation->cancelled_at = now();
            $cancellation->save();

            $this->audit->log('service.cancelled', $service, null, $cancellation->toArray(), $service->branch_id);
            ServiceCancelled::dispatch($service->id, (int) $service->branch_id, $cancellation->reason);

            return $service->fresh();
        });
    }
}
