<?php

namespace App\Services\Inventory;

use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdjustmentService
{
    public function __construct(
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * Controlled stock adjustment — requires approval actor and reason.
     *
     * @param  array<string, mixed>  $data
     */
    public function adjust(array $data, User $approver): StockTransaction
    {
        if (empty($data['reason'])) {
            throw new InvalidArgumentException('Adjustment requires reason.');
        }
        if (empty($data['branch_id']) || empty($data['product_id']) || empty($data['location_id'])) {
            throw new InvalidArgumentException('branch_id, product_id, location_id required.');
        }

        return DB::transaction(function () use ($data, $approver) {
            $txn = $this->ledger->postTransaction([
                'type' => StockTransaction::TYPE_ADJUSTMENT,
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'qty' => $data['qty'],
                'from_location_id' => $data['location_id'],
                'to_location_id' => $data['location_id'],
                'approved_by' => $approver->id,
                'reason' => $data['reason'],
                'idempotency_key' => $data['idempotency_key'] ?? ('adj-'.Str::uuid()),
                'meta' => ['approved' => true],
            ], $approver);

            $this->audit->log('inventory.adjustment.posted', $txn, null, [
                'txn_number' => $txn->txn_number,
                'qty' => $data['qty'],
                'reason' => $data['reason'],
            ], (int) $data['branch_id'], $data['reason']);

            return $txn;
        });
    }
}
