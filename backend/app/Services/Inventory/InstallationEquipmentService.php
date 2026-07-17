<?php

namespace App\Services\Inventory;

use App\Events\Inventory\EquipmentInstalled;
use App\Events\Inventory\EquipmentReturned;
use App\Models\Inventory\CustomerEquipment;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Location;
use App\Models\Inventory\Reservation;
use App\Models\Inventory\StockTransaction;
use App\Models\Tickets\Installation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InstallationEquipmentService
{
    public function __construct(
        private ReservationService $reservations,
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{product_id:int, qty:float|int, equipment_id?:int|null}>  $items
     */
    public function createReservationFromInstallation(
        Installation $installation,
        int $locationId,
        array $items,
        ?User $actor = null,
    ): Reservation {
        if (! in_array($installation->status, [
            Installation::STATUS_EQUIPMENT_WAITING,
            Installation::STATUS_SCHEDULED,
            Installation::STATUS_ASSIGNED,
            Installation::STATUS_FINANCE_REVIEW,
        ], true)) {
            throw new InvalidArgumentException('Installation status does not allow equipment reservation.');
        }

        return $this->reservations->create([
            'branch_id' => $installation->branch_id,
            'location_id' => $locationId,
            'installation_id' => $installation->id,
            'customer_id' => $installation->customer_id,
            'notes' => 'Installation '.$installation->installation_number,
        ], $items, $actor);
    }

    public function fulfillForInstallation(Reservation $reservation, ?User $actor = null): Reservation
    {
        $this->reservations->reserve($reservation, $actor);

        return $this->reservations->fulfill($reservation, $actor);
    }

    public function installAtCustomer(
        Equipment $equipment,
        Installation $installation,
        ?User $actor = null,
        ?string $serviceRef = null,
    ): CustomerEquipment {
        return DB::transaction(function () use ($equipment, $installation, $actor, $serviceRef) {
            if ($equipment->location_id) {
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_INSTALLATION,
                    'branch_id' => $equipment->branch_id,
                    'product_id' => $equipment->product_id,
                    'equipment_id' => $equipment->id,
                    'qty' => 1,
                    'from_location_id' => $equipment->location_id,
                    'installation_id' => $installation->id,
                    'customer_id' => $installation->customer_id,
                    'idempotency_key' => 'install-eq-'.$equipment->id.'-inst-'.$installation->id,
                ], $actor);
            }

            $equipment->status = Equipment::STATUS_INSTALLED;
            $equipment->customer_id = $installation->customer_id;
            $equipment->installation_id = $installation->id;
            $equipment->service_ref = $serviceRef;
            $equipment->location_id = null;
            $equipment->save();

            $record = CustomerEquipment::query()->create([
                'customer_id' => $installation->customer_id,
                'branch_id' => $installation->branch_id,
                'service_ref' => $serviceRef,
                'product_id' => $equipment->product_id,
                'equipment_id' => $equipment->id,
                'ownership_type' => 'company_owned',
                'installed_at' => now()->toDateString(),
                'installation_id' => $installation->id,
                'status' => 'installed',
            ]);

            EquipmentInstalled::dispatch($equipment->id, (int) $equipment->branch_id, (int) $installation->id);

            return $record;
        });
    }

    public function returnUnused(Equipment $equipment, int $toLocationId, ?User $actor = null): Equipment
    {
        return DB::transaction(function () use ($equipment, $toLocationId, $actor) {
            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_RETURN,
                'branch_id' => $equipment->branch_id,
                'product_id' => $equipment->product_id,
                'equipment_id' => $equipment->id,
                'qty' => 1,
                'to_location_id' => $toLocationId,
                'idempotency_key' => 'eq-return-'.$equipment->id.'-'.Str::uuid(),
            ], $actor);

            $equipment->status = Equipment::STATUS_IN_STOCK;
            $equipment->location_id = $toLocationId;
            $equipment->installation_id = null;
            $equipment->save();

            EquipmentReturned::dispatch($equipment->id, (int) $equipment->branch_id);

            return $equipment->fresh();
        });
    }

    /**
     * Block installation completion when reserved equipment is unaccounted.
     */
    public function ensureEquipmentReconciled(Installation $installation): void
    {
        $open = Reservation::query()
            ->where('installation_id', $installation->id)
            ->whereIn('status', [
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_PARTIALLY_FULFILLED,
                Reservation::STATUS_DRAFT,
                Reservation::STATUS_PENDING_APPROVAL,
            ])
            ->exists();

        if ($open) {
            throw new InvalidArgumentException('Installation has unaccounted equipment reservations.');
        }

        $unaccounted = Equipment::query()
            ->where('installation_id', $installation->id)
            ->whereNotIn('status', [Equipment::STATUS_INSTALLED, Equipment::STATUS_SOLD, Equipment::STATUS_IN_STOCK])
            ->where('status', Equipment::STATUS_RESERVED)
            ->exists();

        if ($unaccounted) {
            throw new InvalidArgumentException('Installation has unaccounted reserved equipment.');
        }
    }
}
