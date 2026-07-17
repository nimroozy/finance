<?php

namespace App\Services\Inventory;

use App\Events\Inventory\StockTransactionPosted;
use App\Models\Inventory\Location;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\Inventory\TxnSequence;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StockLedgerService
{
    public function __construct(
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function postTransaction(array $data, ?User $actor = null): StockTransaction
    {
        $type = (string) ($data['type'] ?? '');
        if ($type === '') {
            throw new InvalidArgumentException('Transaction type is required.');
        }

        $branchId = (int) ($data['branch_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        $qty = (float) ($data['qty'] ?? 0);

        if ($branchId <= 0 || $productId <= 0) {
            throw new InvalidArgumentException('branch_id and product_id are required.');
        }

        if ($qty == 0.0) {
            throw new InvalidArgumentException('qty must be non-zero.');
        }

        $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('idempotency_key is required.');
        }

        $existing = StockTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $product = Product::query()->findOrFail($productId);
        if ($product->tracking_mode === Product::TRACK_NON_STOCK) {
            throw new InvalidArgumentException('Non-stock service products cannot post stock transactions.');
        }

        return DB::transaction(function () use ($data, $actor, $type, $branchId, $productId, $qty, $idempotencyKey, $product) {
            $again = StockTransaction::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($again) {
                return $again;
            }

            $fromId = isset($data['from_location_id']) ? (int) $data['from_location_id'] : null;
            $toId = isset($data['to_location_id']) ? (int) $data['to_location_id'] : null;
            $absQty = abs($qty);
            $effectQty = $type === StockTransaction::TYPE_ADJUSTMENT ? $qty : $absQty;

            $fromBalance = $fromId ? $this->lockBalance($branchId, $fromId, $productId) : null;
            $toBalance = $toId ? $this->lockBalance($branchId, $toId, $productId) : null;

            $this->applyBalanceEffects($type, $effectQty, $fromBalance, $toBalance, $fromId, $toId);

            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : null;
            $txn = StockTransaction::query()->create([
                'txn_number' => $this->nextTxnNumber($branchId),
                'type' => $type,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'equipment_id' => $data['equipment_id'] ?? null,
                'qty' => $absQty,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? round($unitCost * $absQty, 2) : ($data['total_cost'] ?? null),
                'from_location_id' => $fromId,
                'to_location_id' => $toId,
                'customer_id' => $data['customer_id'] ?? null,
                'installation_id' => $data['installation_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'tower_id' => $data['tower_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'sale_id' => $data['sale_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'reference' => $data['reference'] ?? null,
                'created_by' => $actor?->id ?? ($data['created_by'] ?? null),
                'approved_by' => $data['approved_by'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
                'idempotency_key' => $idempotencyKey,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'meta' => $data['meta'] ?? null,
                'created_at' => now(),
            ]);

            $this->audit->log('inventory.stock_transaction.posted', $txn, null, [
                'txn_number' => $txn->txn_number,
                'type' => $type,
                'qty' => $absQty,
                'product_id' => $productId,
            ], $branchId);

            StockTransactionPosted::dispatch($txn->id, $branchId, $type);

            return $txn;
        });
    }

    public function availableAt(int $locationId, int $productId): float
    {
        $balance = StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->first();

        return $balance ? $balance->available() : 0.0;
    }

    private function lockBalance(int $branchId, int $locationId, int $productId): StockBalance
    {
        $balance = StockBalance::query()
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            StockBalance::query()->create([
                'branch_id' => $branchId,
                'location_id' => $locationId,
                'product_id' => $productId,
                'on_hand' => 0,
                'reserved' => 0,
                'in_transit' => 0,
                'damaged' => 0,
                'quarantine' => 0,
            ]);

            $balance = StockBalance::query()
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $balance;
    }

    private function applyBalanceEffects(
        string $type,
        float $qty,
        ?StockBalance $from,
        ?StockBalance $to,
        ?int $fromId,
        ?int $toId,
    ): void {
        match ($type) {
            StockTransaction::TYPE_RECEIPT => $this->receipt($to, $toId, $qty),
            StockTransaction::TYPE_TRANSFER => $this->immediateTransfer($from, $to, $fromId, $toId, $qty),
            StockTransaction::TYPE_TRANSFER_DISPATCH => $this->dispatch($from, $to, $fromId, $toId, $qty),
            StockTransaction::TYPE_TRANSFER_RECEIVE => $this->receive($from, $to, $fromId, $toId, $qty),
            StockTransaction::TYPE_RESERVATION => $this->reserve($from ?? $to, $fromId ?? $toId, $qty),
            StockTransaction::TYPE_RELEASE => $this->release($from ?? $to, $fromId ?? $toId, $qty),
            StockTransaction::TYPE_SALE,
            StockTransaction::TYPE_INSTALLATION,
            StockTransaction::TYPE_ISSUE,
            StockTransaction::TYPE_CUSTODY => $this->issue($from, $fromId, $qty, consumeReserved: true),
            StockTransaction::TYPE_RETURN => $this->receipt($to, $toId, $qty),
            StockTransaction::TYPE_REPAIR => $this->immediateTransfer($from, $to, $fromId, $toId, $qty),
            StockTransaction::TYPE_DAMAGE => $this->toBucket($from, $fromId, $qty, 'damaged'),
            StockTransaction::TYPE_LOSS,
            StockTransaction::TYPE_SCRAP => $this->writeOff($from, $fromId, $qty),
            StockTransaction::TYPE_ADJUSTMENT => $this->adjust($to ?? $from, $toId ?? $fromId, $qty, $from, $to),
            default => throw new InvalidArgumentException("Unsupported stock transaction type [{$type}]."),
        };
    }

    private function receipt(?StockBalance $to, ?int $toId, float $qty): void
    {
        if (! $to || ! $toId) {
            throw new InvalidArgumentException('Receipt requires to_location_id.');
        }
        $to->on_hand = (float) $to->on_hand + $qty;
        $to->save();
    }

    private function immediateTransfer(?StockBalance $from, ?StockBalance $to, ?int $fromId, ?int $toId, float $qty): void
    {
        if (! $from || ! $to || ! $fromId || ! $toId) {
            throw new InvalidArgumentException('Transfer requires from and to locations.');
        }
        $this->assertAvailable($from, $fromId, $qty);
        $from->on_hand = (float) $from->on_hand - $qty;
        $from->save();
        $to->on_hand = (float) $to->on_hand + $qty;
        $to->save();
    }

    private function dispatch(?StockBalance $from, ?StockBalance $to, ?int $fromId, ?int $toId, float $qty): void
    {
        if (! $from || ! $fromId) {
            throw new InvalidArgumentException('Dispatch requires from_location_id.');
        }
        $this->assertAvailable($from, $fromId, $qty);
        $from->on_hand = (float) $from->on_hand - $qty;
        $from->save();
        if ($to) {
            $to->in_transit = (float) $to->in_transit + $qty;
            $to->save();
        }
    }

    private function receive(?StockBalance $from, ?StockBalance $to, ?int $fromId, ?int $toId, float $qty): void
    {
        if (! $to || ! $toId) {
            throw new InvalidArgumentException('Receive requires to_location_id.');
        }
        if ($from) {
            $from->in_transit = max(0, (float) $from->in_transit - $qty);
            $from->save();
        }
        $to->on_hand = (float) $to->on_hand + $qty;
        if ((float) $to->in_transit > 0) {
            $to->in_transit = max(0, (float) $to->in_transit - $qty);
        }
        $to->save();
    }

    private function reserve(?StockBalance $balance, ?int $locationId, float $qty): void
    {
        if (! $balance || ! $locationId) {
            throw new InvalidArgumentException('Reservation requires a location.');
        }
        $this->assertAvailable($balance, $locationId, $qty);
        $balance->reserved = (float) $balance->reserved + $qty;
        $balance->save();
    }

    private function release(?StockBalance $balance, ?int $locationId, float $qty): void
    {
        if (! $balance || ! $locationId) {
            throw new InvalidArgumentException('Release requires a location.');
        }
        $balance->reserved = max(0, (float) $balance->reserved - $qty);
        $balance->save();
    }

    private function issue(?StockBalance $from, ?int $fromId, float $qty, bool $consumeReserved = false): void
    {
        if (! $from || ! $fromId) {
            throw new InvalidArgumentException('Issue requires from_location_id.');
        }
        if ($consumeReserved && (float) $from->reserved > 0) {
            $consume = min((float) $from->reserved, $qty);
            $from->reserved = (float) $from->reserved - $consume;
        }
        $available = (float) $from->on_hand - (float) $from->reserved;
        $location = Location::query()->find($fromId);
        if ($available < $qty && ! ($location?->allow_negative)) {
            throw new InvalidArgumentException('Insufficient available stock.');
        }
        $from->on_hand = (float) $from->on_hand - $qty;
        $from->save();
    }

    private function toBucket(?StockBalance $from, ?int $fromId, float $qty, string $bucket): void
    {
        if (! $from || ! $fromId) {
            throw new InvalidArgumentException("{$bucket} requires from_location_id.");
        }
        $this->assertAvailable($from, $fromId, $qty);
        $from->on_hand = (float) $from->on_hand - $qty;
        $from->{$bucket} = (float) $from->{$bucket} + $qty;
        $from->save();
    }

    private function writeOff(?StockBalance $from, ?int $fromId, float $qty): void
    {
        if (! $from || ! $fromId) {
            throw new InvalidArgumentException('Write-off requires from_location_id.');
        }
        $this->assertAvailable($from, $fromId, $qty);
        $from->on_hand = (float) $from->on_hand - $qty;
        $from->save();
    }

    private function adjust(?StockBalance $primary, ?int $primaryId, float $qty, ?StockBalance $from, ?StockBalance $to): void
    {
        // Positive qty increases on_hand at to/primary; negative decreases at from/primary.
        $signed = $qty;
        if ($to && $from && $from->id !== $to->id) {
            // treat as transfer-like adjustment
            $this->immediateTransfer($from, $to, (int) $from->location_id, (int) $to->location_id, abs($signed));

            return;
        }
        if (! $primary || ! $primaryId) {
            throw new InvalidArgumentException('Adjustment requires a location.');
        }
        $newOnHand = (float) $primary->on_hand + $signed;
        $location = Location::query()->find($primaryId);
        if ($newOnHand < 0 && ! ($location?->allow_negative)) {
            throw new InvalidArgumentException('Adjustment would create negative stock.');
        }
        $primary->on_hand = $newOnHand;
        $primary->save();
    }

    private function assertAvailable(StockBalance $balance, int $locationId, float $qty): void
    {
        $available = (float) $balance->on_hand - (float) $balance->reserved;
        $location = Location::query()->find($locationId);
        if ($available < $qty && ! ($location?->allow_negative)) {
            throw new InvalidArgumentException('Insufficient available stock.');
        }
    }

    private function nextTxnNumber(int $branchId): string
    {
        $year = (int) now()->format('Y');
        $seq = TxnSequence::query()
            ->where('branch_id', $branchId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $seq) {
            $seq = TxnSequence::query()->create([
                'branch_id' => $branchId,
                'year' => $year,
                'last_sequence' => 0,
            ]);
            $seq = TxnSequence::query()
                ->where('branch_id', $branchId)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_sequence = (int) $seq->last_sequence + 1;
        $seq->save();

        return sprintf('STX-%d-%d-%06d', $branchId, $year, $seq->last_sequence);
    }
}
