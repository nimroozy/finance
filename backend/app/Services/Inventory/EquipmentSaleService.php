<?php

namespace App\Services\Inventory;

use App\Models\Inventory\CustomerEquipment;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\EquipmentSale;
use App\Models\Inventory\EquipmentSaleItem;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EquipmentSaleService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private StockLedgerService $ledger,
        private ReservationService $reservations,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id:int, qty:float|int, unit_price?:float|null, equipment_id?:int|null}>  $items
     */
    public function create(array $data, array $items, ?User $actor = null): EquipmentSale
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        if ($branchId <= 0 || empty($data['customer_id']) || $items === []) {
            throw new InvalidArgumentException('branch_id, customer_id, items required.');
        }

        return DB::transaction(function () use ($data, $items, $actor, $branchId) {
            $total = 0.0;
            foreach ($items as $item) {
                $total += ((float) ($item['unit_price'] ?? 0)) * ((float) $item['qty']);
            }

            $sale = EquipmentSale::query()->create([
                'sale_number' => $this->numbers->sale($branchId),
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'],
                'location_id' => $data['location_id'] ?? null,
                'status' => 'draft',
                'total_amount' => $total,
                'zoho_sync_status' => 'pending',
                'zoho_idempotency_key' => (string) Str::uuid(),
                'created_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                EquipmentSaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'equipment_id' => $item['equipment_id'] ?? null,
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'] ?? null,
                ]);
            }

            return $sale->fresh('items');
        });
    }

    public function reserveAndIssue(EquipmentSale $sale, ?User $actor = null): EquipmentSale
    {
        if (! $sale->location_id) {
            throw new InvalidArgumentException('Sale location required.');
        }

        return DB::transaction(function () use ($sale, $actor) {
            $rsvItems = $sale->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'qty' => $i->qty,
                'equipment_id' => $i->equipment_id,
            ])->all();

            $reservation = $this->reservations->create([
                'branch_id' => $sale->branch_id,
                'location_id' => $sale->location_id,
                'customer_id' => $sale->customer_id,
                'notes' => 'Sale '.$sale->sale_number,
            ], $rsvItems, $actor);

            $this->reservations->reserve($reservation, $actor);
            $this->reservations->fulfill($reservation, $actor);

            foreach ($sale->items as $item) {
                if ($item->equipment_id) {
                    $eq = Equipment::query()->find($item->equipment_id);
                    if ($eq) {
                        $eq->status = Equipment::STATUS_SOLD;
                        $eq->ownership = 'customer';
                        $eq->customer_id = $sale->customer_id;
                        $eq->save();
                    }
                }

                CustomerEquipment::query()->create([
                    'customer_id' => $sale->customer_id,
                    'branch_id' => $sale->branch_id,
                    'product_id' => $item->product_id,
                    'equipment_id' => $item->equipment_id,
                    'ownership_type' => 'customer_owned',
                    'sale_price' => $item->unit_price,
                    'installed_at' => now()->toDateString(),
                    'status' => 'sold',
                ]);
            }

            $sale->status = 'completed';
            $sale->save();

            $this->audit->log('inventory.equipment_sale.completed', $sale, null, $sale->toArray(), (int) $sale->branch_id);

            return $sale->fresh('items');
        });
    }
}
