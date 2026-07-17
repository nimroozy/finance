<?php

namespace App\Services\Inventory;

use App\Events\Inventory\TransferDispatched;
use App\Events\Inventory\TransferReceived;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\Transfer;
use App\Models\Inventory\TransferItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransferService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id:int, qty:float|int, equipment_id?:int|null}>  $items
     */
    public function create(array $data, array $items, ?User $actor = null): Transfer
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        if ($branchId <= 0 || empty($data['from_location_id']) || empty($data['to_location_id'])) {
            throw new InvalidArgumentException('branch_id, from_location_id, to_location_id required.');
        }
        if ($items === []) {
            throw new InvalidArgumentException('Transfer items required.');
        }

        return DB::transaction(function () use ($data, $items, $actor, $branchId) {
            $transfer = Transfer::query()->create([
                'transfer_number' => $this->numbers->transfer($branchId),
                'branch_id' => $branchId,
                'from_location_id' => $data['from_location_id'],
                'to_location_id' => $data['to_location_id'],
                'status' => Transfer::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            foreach ($items as $item) {
                TransferItem::query()->create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'equipment_id' => $item['equipment_id'] ?? null,
                    'qty_requested' => $item['qty'],
                ]);
            }

            $this->audit->log('inventory.transfer.created', $transfer, null, $transfer->toArray(), $branchId);

            return $transfer->fresh('items');
        });
    }

    public function submit(Transfer $transfer, ?User $actor = null): Transfer
    {
        $this->assertStatus($transfer, [Transfer::STATUS_DRAFT]);
        $transfer->status = Transfer::STATUS_SUBMITTED;
        $transfer->submitted_at = now();
        $transfer->save();

        return $transfer;
    }

    public function approve(Transfer $transfer, ?User $actor = null): Transfer
    {
        $this->assertStatus($transfer, [Transfer::STATUS_SUBMITTED, Transfer::STATUS_DRAFT]);
        $transfer->status = Transfer::STATUS_APPROVED;
        $transfer->approved_by = $actor?->id;
        $transfer->approved_at = now();
        $transfer->save();

        return $transfer;
    }

    public function dispatch(Transfer $transfer, ?User $actor = null): Transfer
    {
        $this->assertStatus($transfer, [Transfer::STATUS_APPROVED]);

        return DB::transaction(function () use ($transfer, $actor) {
            foreach ($transfer->items as $item) {
                $qty = (float) $item->qty_requested;
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_TRANSFER_DISPATCH,
                    'branch_id' => $transfer->branch_id,
                    'product_id' => $item->product_id,
                    'equipment_id' => $item->equipment_id,
                    'qty' => $qty,
                    'from_location_id' => $transfer->from_location_id,
                    'to_location_id' => $transfer->to_location_id,
                    'idempotency_key' => 'tr-disp-'.$transfer->id.'-item-'.$item->id,
                    'reference' => $transfer->transfer_number,
                ], $actor);
                $item->qty_dispatched = $qty;
                $item->save();
            }

            $transfer->status = Transfer::STATUS_IN_TRANSIT;
            $transfer->dispatched_by = $actor?->id;
            $transfer->dispatched_at = now();
            $transfer->save();

            TransferDispatched::dispatch($transfer->id, (int) $transfer->branch_id);

            return $transfer->fresh('items');
        });
    }

    /**
     * @param  array<int, float|int>|null  $receivedQtys  keyed by transfer_item id
     */
    public function receive(Transfer $transfer, ?User $actor = null, ?array $receivedQtys = null): Transfer
    {
        $this->assertStatus($transfer, [Transfer::STATUS_IN_TRANSIT, Transfer::STATUS_DISPATCHED]);

        return DB::transaction(function () use ($transfer, $actor, $receivedQtys) {
            $hasDiscrepancy = false;
            foreach ($transfer->items as $item) {
                $qty = $receivedQtys[$item->id] ?? (float) $item->qty_dispatched;
                $qty = (float) $qty;
                if ($qty < (float) $item->qty_dispatched) {
                    $hasDiscrepancy = true;
                    $item->discrepancy_notes = $item->discrepancy_notes ?? 'Partial receipt';
                }

                if ($qty > 0) {
                    $this->ledger->postTransaction([
                        'type' => StockTransaction::TYPE_TRANSFER_RECEIVE,
                        'branch_id' => $transfer->branch_id,
                        'product_id' => $item->product_id,
                        'equipment_id' => $item->equipment_id,
                        'qty' => $qty,
                        'from_location_id' => $transfer->from_location_id,
                        'to_location_id' => $transfer->to_location_id,
                        'idempotency_key' => 'tr-recv-'.$transfer->id.'-item-'.$item->id.'-'.$qty,
                        'reference' => $transfer->transfer_number,
                    ], $actor);
                }

                $item->qty_received = (float) $item->qty_received + $qty;
                $item->save();
            }

            $transfer->has_discrepancy = $hasDiscrepancy;
            $transfer->status = Transfer::STATUS_RECEIVED;
            $transfer->received_by = $actor?->id;
            $transfer->received_at = now();
            $transfer->save();

            TransferReceived::dispatch($transfer->id, (int) $transfer->branch_id);

            return $transfer->fresh('items');
        });
    }

    public function close(Transfer $transfer, ?User $actor = null): Transfer
    {
        $this->assertStatus($transfer, [Transfer::STATUS_RECEIVED]);
        $transfer->status = Transfer::STATUS_CLOSED;
        $transfer->save();

        return $transfer;
    }

    /** @param list<string> $allowed */
    private function assertStatus(Transfer $transfer, array $allowed): void
    {
        if (! in_array($transfer->status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid transfer status '.$transfer->status);
        }
    }
}
