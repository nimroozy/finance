<?php

namespace App\Services\Inventory;

use App\Events\Inventory\PlaceholderZohoPurchaseSyncRequested;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\PurchaseRequest;
use App\Models\Inventory\PurchaseRequestItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchasingService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id:int, qty:float|int}>  $items
     */
    public function createRequest(array $data, array $items, ?User $actor = null): PurchaseRequest
    {
        $branchId = (int) ($data['branch_id'] ?? 0);

        return DB::transaction(function () use ($data, $items, $actor, $branchId) {
            $pr = PurchaseRequest::query()->create([
                'request_number' => $this->numbers->purchaseRequest($branchId),
                'branch_id' => $branchId,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'requested_by' => $actor?->id,
            ]);
            foreach ($items as $item) {
                PurchaseRequestItem::query()->create([
                    'purchase_request_id' => $pr->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
            $this->audit->log('inventory.purchase_request.created', $pr, null, $pr->toArray(), $branchId);

            return $pr->fresh('items');
        });
    }

    public function approveRequest(PurchaseRequest $pr, ?User $actor = null): PurchaseRequest
    {
        $pr->status = 'approved';
        $pr->approved_by = $actor?->id;
        $pr->save();

        return $pr;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id:int, qty:float|int, unit_cost?:float|null}>  $items
     */
    public function createOrder(array $data, array $items, ?User $actor = null): PurchaseOrder
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        if (empty($data['supplier_id'])) {
            throw new InvalidArgumentException('supplier_id required.');
        }

        return DB::transaction(function () use ($data, $items, $actor, $branchId) {
            $total = 0.0;
            foreach ($items as $item) {
                $total += ((float) ($item['unit_cost'] ?? 0)) * ((float) $item['qty']);
            }

            $po = PurchaseOrder::query()->create([
                'po_number' => $this->numbers->po($branchId),
                'branch_id' => $branchId,
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'status' => 'draft',
                'expected_date' => $data['expected_date'] ?? null,
                'total_amount' => $total,
                'zoho_sync_status' => 'pending',
                'zoho_idempotency_key' => (string) Str::uuid(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'qty_ordered' => $item['qty'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                ]);
            }

            $this->audit->log('inventory.purchase_order.created', $po, null, $po->toArray(), $branchId);

            return $po->fresh('items');
        });
    }

    public function approveOrder(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        $po->status = 'approved';
        $po->approved_by = $actor?->id;
        $po->save();

        PlaceholderZohoPurchaseSyncRequested::dispatch($po->id, (int) $po->branch_id);

        return $po;
    }
}
