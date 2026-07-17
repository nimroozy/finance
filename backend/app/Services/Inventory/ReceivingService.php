<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Equipment;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\GoodsReceiptItem;
use App\Models\Inventory\Product;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReceivingService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private StockLedgerService $ledger,
        private EquipmentService $equipment,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id:int, qty:float|int, unit_cost?:float|null, serial_number?:string|null}>  $items
     */
    public function createAndPost(array $data, array $items, ?User $actor = null): GoodsReceipt
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        $locationId = (int) ($data['location_id'] ?? 0);
        if ($branchId <= 0 || $locationId <= 0 || $items === []) {
            throw new InvalidArgumentException('branch_id, location_id, and items required.');
        }

        return DB::transaction(function () use ($data, $items, $actor, $branchId, $locationId) {
            $grn = GoodsReceipt::query()->create([
                'grn_number' => $this->numbers->grn($branchId),
                'branch_id' => $branchId,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'location_id' => $locationId,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $equipmentId = null;
                $serial = $item['serial_number'] ?? null;

                if ($product->isSerialized()) {
                    if (! $serial) {
                        throw new InvalidArgumentException('serial_number required for serialized product.');
                    }
                    $eq = $this->equipment->receiveSerial([
                        'product_id' => $product->id,
                        'serial_number' => $serial,
                        'branch_id' => $branchId,
                        'location_id' => $locationId,
                        'supplier_id' => $data['supplier_id'] ?? null,
                        'purchase_cost' => $item['unit_cost'] ?? null,
                        'idempotency_key' => 'grn-eq-'.$grn->id.'-'.$serial,
                    ], $actor);
                    $equipmentId = $eq->id;
                } else {
                    $this->ledger->postTransaction([
                        'type' => StockTransaction::TYPE_RECEIPT,
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                        'qty' => $item['qty'],
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'to_location_id' => $locationId,
                        'supplier_id' => $data['supplier_id'] ?? null,
                        'purchase_order_id' => $data['purchase_order_id'] ?? null,
                        'idempotency_key' => 'grn-'.$grn->id.'-p-'.$product->id.'-'.Str::uuid(),
                        'reference' => $grn->grn_number,
                        'reason' => 'purchase_receipt',
                    ], $actor);
                }

                GoodsReceiptItem::query()->create([
                    'goods_receipt_id' => $grn->id,
                    'product_id' => $product->id,
                    'equipment_id' => $equipmentId,
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'serial_number' => $serial,
                ]);

                if (! empty($data['purchase_order_id'])) {
                    $poi = PurchaseOrder::query()->find($data['purchase_order_id'])
                        ?->items()
                        ->where('product_id', $product->id)
                        ->first();
                    if ($poi) {
                        $poi->qty_received = (float) $poi->qty_received + (float) $item['qty'];
                        $poi->save();
                    }
                }
            }

            $grn->status = 'posted';
            $grn->received_at = now();
            $grn->received_by = $actor?->id;
            $grn->save();

            if (! empty($data['purchase_order_id'])) {
                $po = PurchaseOrder::query()->with('items')->find($data['purchase_order_id']);
                if ($po) {
                    $all = $po->items->every(fn ($i) => (float) $i->qty_received >= (float) $i->qty_ordered);
                    $any = $po->items->contains(fn ($i) => (float) $i->qty_received > 0);
                    $po->status = $all ? 'received' : ($any ? 'partially_received' : $po->status);
                    $po->save();
                }
            }

            $this->audit->log('inventory.goods_receipt.posted', $grn, null, $grn->toArray(), $branchId);

            return $grn->fresh('items');
        });
    }
}
