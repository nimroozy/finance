<?php

namespace App\Services\Inventory;

use App\Events\Inventory\StockCountPosted;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockCountLine;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockCountService
{
    public function __construct(
        private InventoryNumberService $numbers,
        private StockLedgerService $ledger,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): StockCount
    {
        $branchId = (int) ($data['branch_id'] ?? 0);
        $locationId = (int) ($data['location_id'] ?? 0);

        return DB::transaction(function () use ($data, $actor, $branchId, $locationId) {
            $count = StockCount::query()->create([
                'count_number' => $this->numbers->count($branchId),
                'branch_id' => $branchId,
                'location_id' => $locationId,
                'status' => 'draft',
                'counted_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $balances = StockBalance::query()
                ->where('location_id', $locationId)
                ->get();

            foreach ($balances as $balance) {
                StockCountLine::query()->create([
                    'stock_count_id' => $count->id,
                    'product_id' => $balance->product_id,
                    'system_qty' => $balance->on_hand,
                ]);
            }

            return $count->fresh('lines');
        });
    }

    /** @param array<int, float|int> $counted keyed by line id */
    public function recordCounts(StockCount $count, array $counted): StockCount
    {
        foreach ($count->lines as $line) {
            if (! array_key_exists($line->id, $counted)) {
                continue;
            }
            $line->counted_qty = $counted[$line->id];
            $line->variance_qty = (float) $line->counted_qty - (float) $line->system_qty;
            $line->save();
        }
        $count->status = 'in_progress';
        $count->save();

        return $count->fresh('lines');
    }

    public function post(StockCount $count, ?User $actor = null): StockCount
    {
        if (! in_array($count->status, ['draft', 'in_progress'], true)) {
            throw new InvalidArgumentException('Stock count already posted.');
        }

        return DB::transaction(function () use ($count, $actor) {
            foreach ($count->lines as $line) {
                if ($line->counted_qty === null) {
                    continue;
                }
                $variance = (float) $line->variance_qty;
                if ($variance == 0.0) {
                    continue;
                }
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_ADJUSTMENT,
                    'branch_id' => $count->branch_id,
                    'product_id' => $line->product_id,
                    'qty' => $variance,
                    'to_location_id' => $count->location_id,
                    'from_location_id' => $count->location_id,
                    'approved_by' => $actor?->id,
                    'idempotency_key' => 'count-'.$count->id.'-line-'.$line->id,
                    'reason' => 'stock_count',
                    'reference' => $count->count_number,
                ], $actor);
            }

            $count->status = 'posted';
            $count->approved_by = $actor?->id;
            $count->posted_at = now();
            $count->save();

            StockCountPosted::dispatch($count->id, (int) $count->branch_id);
            $this->audit->log('inventory.stock_count.posted', $count, null, $count->toArray(), (int) $count->branch_id);

            return $count->fresh('lines');
        });
    }
}
