<?php

namespace App\Services\Inventory;

use App\Events\Inventory\RepairCompleted;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\Repair;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RepairService
{
    public function __construct(
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function open(array $data, ?User $actor = null): Repair
    {
        $equipment = Equipment::query()->findOrFail($data['equipment_id'] ?? 0);
        $repairLocationId = (int) ($data['repair_location_id'] ?? 0);
        if ($repairLocationId <= 0) {
            throw new InvalidArgumentException('repair_location_id required.');
        }

        return DB::transaction(function () use ($data, $actor, $equipment, $repairLocationId) {
            $from = (int) ($equipment->location_id ?? $data['from_location_id'] ?? 0);
            if ($from <= 0) {
                throw new InvalidArgumentException('Equipment location required.');
            }

            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_REPAIR,
                'branch_id' => $equipment->branch_id,
                'product_id' => $equipment->product_id,
                'equipment_id' => $equipment->id,
                'qty' => 1,
                'from_location_id' => $from,
                'to_location_id' => $repairLocationId,
                'idempotency_key' => 'repair-open-'.$equipment->id.'-'.now()->timestamp,
            ], $actor);

            $equipment->status = Equipment::STATUS_REPAIR;
            $equipment->location_id = $repairLocationId;
            $equipment->save();

            $repair = Repair::query()->create([
                'branch_id' => $equipment->branch_id,
                'equipment_id' => $equipment->id,
                'from_location_id' => $from,
                'repair_location_id' => $repairLocationId,
                'status' => 'open',
                'fault_description' => $data['fault_description'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'started_at' => now(),
            ]);

            $this->audit->log('inventory.repair.opened', $repair, null, $repair->toArray(), (int) $repair->branch_id);

            return $repair;
        });
    }

    public function complete(Repair $repair, int $returnLocationId, ?User $actor = null, ?string $notes = null, ?float $cost = null): Repair
    {
        return DB::transaction(function () use ($repair, $returnLocationId, $actor, $notes, $cost) {
            $equipment = Equipment::query()->findOrFail($repair->equipment_id);
            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_TRANSFER,
                'branch_id' => $repair->branch_id,
                'product_id' => $equipment->product_id,
                'equipment_id' => $equipment->id,
                'qty' => 1,
                'from_location_id' => $repair->repair_location_id,
                'to_location_id' => $returnLocationId,
                'idempotency_key' => 'repair-done-'.$repair->id,
            ], $actor);

            $equipment->status = Equipment::STATUS_IN_STOCK;
            $equipment->location_id = $returnLocationId;
            $equipment->condition = 'good';
            $equipment->save();

            $repair->status = 'completed';
            $repair->resolution_notes = $notes;
            $repair->repair_cost = $cost;
            $repair->completed_at = now();
            $repair->save();

            RepairCompleted::dispatch($repair->id, (int) $repair->branch_id);

            return $repair->fresh();
        });
    }
}
