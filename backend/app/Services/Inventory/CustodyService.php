<?php

namespace App\Services\Inventory;

use App\Events\Inventory\CustodyIssued;
use App\Models\Inventory\CustodyRecord;
use App\Models\Inventory\Equipment;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustodyService
{
    public function __construct(
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function issue(array $data, ?User $actor = null): CustodyRecord
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        $fromLocationId = (int) ($data['from_location_id'] ?? 0);
        $qty = (float) ($data['qty'] ?? 1);
        if ($branchId <= 0 || $userId <= 0 || $fromLocationId <= 0) {
            throw new InvalidArgumentException('branch_id, user_id, from_location_id required.');
        }

        return DB::transaction(function () use ($data, $actor, $branchId, $userId, $fromLocationId, $qty) {
            $productId = (int) ($data['product_id'] ?? 0);
            $equipmentId = $data['equipment_id'] ?? null;

            if ($equipmentId) {
                $eq = Equipment::query()->findOrFail($equipmentId);
                $productId = (int) $eq->product_id;
                $eq->status = Equipment::STATUS_CUSTODY;
                $eq->custodian_user_id = $userId;
                $eq->save();
            }

            $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_CUSTODY,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'equipment_id' => $equipmentId,
                'qty' => $qty,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $data['custody_location_id'] ?? null,
                'user_id' => $userId,
                'idempotency_key' => $data['idempotency_key'] ?? ('custody-'.Str::uuid()),
            ], $actor);

            if (! empty($data['custody_location_id'])) {
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_RECEIPT,
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'equipment_id' => $equipmentId,
                    'qty' => $qty,
                    'to_location_id' => $data['custody_location_id'],
                    'idempotency_key' => 'custody-recv-'.Str::uuid(),
                ], $actor);
            }

            $record = CustodyRecord::query()->create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'product_id' => $productId,
                'equipment_id' => $equipmentId,
                'from_location_id' => $fromLocationId,
                'custody_location_id' => $data['custody_location_id'] ?? null,
                'qty' => $qty,
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            CustodyIssued::dispatch($record->id, $branchId);
            $this->audit->log('inventory.custody.issued', $record, null, $record->toArray(), $branchId);

            return $record;
        });
    }

    public function returnItem(CustodyRecord $record, int $toLocationId, ?User $actor = null): CustodyRecord
    {
        return DB::transaction(function () use ($record, $toLocationId, $actor) {
            $from = $record->custody_location_id ?? $record->from_location_id;
            if ($from) {
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_TRANSFER,
                    'branch_id' => $record->branch_id,
                    'product_id' => $record->product_id,
                    'equipment_id' => $record->equipment_id,
                    'qty' => $record->qty,
                    'from_location_id' => $from,
                    'to_location_id' => $toLocationId,
                    'idempotency_key' => 'custody-ret-'.$record->id,
                ], $actor);
            } else {
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_RETURN,
                    'branch_id' => $record->branch_id,
                    'product_id' => $record->product_id,
                    'equipment_id' => $record->equipment_id,
                    'qty' => $record->qty,
                    'to_location_id' => $toLocationId,
                    'idempotency_key' => 'custody-ret-'.$record->id,
                ], $actor);
            }

            if ($record->equipment_id) {
                $eq = Equipment::query()->find($record->equipment_id);
                if ($eq) {
                    $eq->status = Equipment::STATUS_IN_STOCK;
                    $eq->custodian_user_id = null;
                    $eq->location_id = $toLocationId;
                    $eq->save();
                }
            }

            $record->status = 'returned';
            $record->returned_at = now();
            $record->save();

            return $record->fresh();
        });
    }
}
