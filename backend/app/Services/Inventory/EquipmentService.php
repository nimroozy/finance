<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Equipment;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EquipmentService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * Receive a serialized unit into stock.
     *
     * @param  array<string, mixed>  $data
     */
    public function receiveSerial(array $data, ?User $actor = null): Equipment
    {
        $product = Product::query()->findOrFail($data['product_id'] ?? 0);
        if (! $product->isSerialized()) {
            throw new InvalidArgumentException('Product is not serialized.');
        }
        $serial = (string) ($data['serial_number'] ?? '');
        if ($serial === '') {
            throw new InvalidArgumentException('serial_number required.');
        }
        if (Equipment::query()->where('product_id', $product->id)->where('serial_number', $serial)->exists()) {
            throw new InvalidArgumentException('Serial already exists for this product.');
        }

        $branchId = (int) ($data['branch_id'] ?? 0);
        $locationId = (int) ($data['location_id'] ?? 0);
        if ($branchId <= 0 || $locationId <= 0) {
            throw new InvalidArgumentException('branch_id and location_id required.');
        }

        return DB::transaction(function () use ($data, $actor, $product, $serial, $branchId, $locationId) {
            $equipment = Equipment::query()->create([
                'equipment_number' => $this->numbers->equipment($branchId),
                'product_id' => $product->id,
                'serial_number' => $serial,
                'mac_address' => $data['mac_address'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'branch_id' => $branchId,
                'location_id' => $locationId,
                'ownership' => $data['ownership'] ?? 'company',
                'condition' => $data['condition'] ?? 'new',
                'status' => Equipment::STATUS_IN_STOCK,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_cost' => $data['purchase_cost'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? null,
                'warranty_start' => $data['warranty_start'] ?? null,
                'warranty_end' => $data['warranty_end'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_RECEIPT,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'equipment_id' => $equipment->id,
                'qty' => 1,
                'unit_cost' => $data['purchase_cost'] ?? null,
                'to_location_id' => $locationId,
                'supplier_id' => $data['supplier_id'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? ('eq-recv-'.$equipment->id.'-'.Str::uuid()),
                'reason' => $data['reason'] ?? 'equipment_receive',
            ], $actor);

            $this->audit->log('inventory.equipment.received', $equipment, null, $equipment->toArray(), $branchId);

            return $equipment->fresh();
        });
    }

    public function move(Equipment $equipment, int $toLocationId, ?User $actor = null, ?string $idempotencyKey = null): Equipment
    {
        if (! $equipment->location_id) {
            throw new InvalidArgumentException('Equipment has no current location.');
        }

        return DB::transaction(function () use ($equipment, $toLocationId, $actor, $idempotencyKey) {
            $from = (int) $equipment->location_id;
            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_TRANSFER,
                'branch_id' => $equipment->branch_id,
                'product_id' => $equipment->product_id,
                'equipment_id' => $equipment->id,
                'qty' => 1,
                'from_location_id' => $from,
                'to_location_id' => $toLocationId,
                'idempotency_key' => $idempotencyKey ?? ('eq-move-'.$equipment->id.'-'.$toLocationId.'-'.Str::uuid()),
            ], $actor);

            $old = $equipment->only(['location_id', 'status']);
            $equipment->location_id = $toLocationId;
            $equipment->status = Equipment::STATUS_IN_STOCK;
            $equipment->save();

            $this->audit->log('inventory.equipment.moved', $equipment, $old, $equipment->only(['location_id', 'status']), (int) $equipment->branch_id);

            return $equipment->fresh();
        });
    }
}
