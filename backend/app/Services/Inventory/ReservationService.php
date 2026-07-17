<?php

namespace App\Services\Inventory;

use App\Events\Inventory\ReservationFulfilled;
use App\Models\Inventory\Location;
use App\Models\Inventory\Reservation;
use App\Models\Inventory\ReservationItem;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReservationService
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
    public function create(array $data, array $items, ?User $actor = null): Reservation
    {
        if ($items === []) {
            throw new InvalidArgumentException('Reservation items required.');
        }
        $branchId = (int) ($data['branch_id'] ?? 0);
        if ($branchId <= 0) {
            throw new InvalidArgumentException('branch_id required.');
        }

        return DB::transaction(function () use ($data, $items, $actor, $branchId) {
            $reservation = Reservation::query()->create([
                'reservation_number' => $this->numbers->reservation($branchId),
                'branch_id' => $branchId,
                'status' => Reservation::STATUS_DRAFT,
                'location_id' => $data['location_id'] ?? null,
                'installation_id' => $data['installation_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            foreach ($items as $item) {
                ReservationItem::query()->create([
                    'reservation_id' => $reservation->id,
                    'product_id' => $item['product_id'],
                    'equipment_id' => $item['equipment_id'] ?? null,
                    'qty_requested' => $item['qty'],
                    'qty_reserved' => 0,
                    'qty_fulfilled' => 0,
                    'qty_released' => 0,
                ]);
            }

            $this->audit->log('inventory.reservation.created', $reservation, null, $reservation->toArray(), $branchId);

            return $reservation->fresh('items');
        });
    }

    public function reserve(Reservation $reservation, ?User $actor = null): Reservation
    {
        if (! in_array($reservation->status, [Reservation::STATUS_DRAFT, Reservation::STATUS_PENDING_APPROVAL, Reservation::STATUS_APPROVED], true)) {
            throw new InvalidArgumentException('Reservation cannot be reserved in status '.$reservation->status);
        }
        if (! $reservation->location_id) {
            throw new InvalidArgumentException('Reservation location required.');
        }

        return DB::transaction(function () use ($reservation, $actor) {
            foreach ($reservation->items as $item) {
                $need = (float) $item->qty_requested - (float) $item->qty_reserved;
                if ($need <= 0) {
                    continue;
                }
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_RESERVATION,
                    'branch_id' => $reservation->branch_id,
                    'product_id' => $item->product_id,
                    'equipment_id' => $item->equipment_id,
                    'qty' => $need,
                    'from_location_id' => $reservation->location_id,
                    'idempotency_key' => 'rsv-'.$reservation->id.'-item-'.$item->id.'-'.(float) $item->qty_requested,
                    'reference' => $reservation->reservation_number,
                ], $actor);
                $item->qty_reserved = $item->qty_requested;
                $item->save();
            }

            $reservation->status = Reservation::STATUS_APPROVED;
            $reservation->approved_by = $actor?->id;
            $reservation->save();

            return $reservation->fresh('items');
        });
    }

    public function release(Reservation $reservation, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            foreach ($reservation->items as $item) {
                $held = (float) $item->qty_reserved - (float) $item->qty_fulfilled - (float) $item->qty_released;
                if ($held <= 0) {
                    continue;
                }
                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_RELEASE,
                    'branch_id' => $reservation->branch_id,
                    'product_id' => $item->product_id,
                    'equipment_id' => $item->equipment_id,
                    'qty' => $held,
                    'from_location_id' => $reservation->location_id,
                    'idempotency_key' => 'rsv-rel-'.$reservation->id.'-item-'.$item->id.'-'.$held,
                    'reference' => $reservation->reservation_number,
                ], $actor);
                $item->qty_released = (float) $item->qty_released + $held;
                $item->save();
            }
            $reservation->status = Reservation::STATUS_RELEASED;
            $reservation->save();

            return $reservation->fresh('items');
        });
    }

    /**
     * Fulfill posts issue (and optional transfer to custody location).
     *
     * @param  array<string, mixed>  $options
     */
    public function fulfill(Reservation $reservation, ?User $actor = null, array $options = []): Reservation
    {
        if (! in_array($reservation->status, [Reservation::STATUS_APPROVED, Reservation::STATUS_PARTIALLY_FULFILLED], true)) {
            throw new InvalidArgumentException('Reservation not fulfillable.');
        }

        return DB::transaction(function () use ($reservation, $actor, $options) {
            $custodyLocationId = $options['custody_location_id'] ?? null;
            $allDone = true;

            foreach ($reservation->items as $item) {
                $pending = (float) $item->qty_reserved - (float) $item->qty_fulfilled;
                if ($pending <= 0) {
                    continue;
                }
                $fulfillQty = isset($options['qty'][$item->id]) ? (float) $options['qty'][$item->id] : $pending;
                $fulfillQty = min($fulfillQty, $pending);

                $this->ledger->postTransaction([
                    'type' => StockTransaction::TYPE_ISSUE,
                    'branch_id' => $reservation->branch_id,
                    'product_id' => $item->product_id,
                    'equipment_id' => $item->equipment_id,
                    'qty' => $fulfillQty,
                    'from_location_id' => $reservation->location_id,
                    'to_location_id' => $custodyLocationId,
                    'installation_id' => $reservation->installation_id,
                    'customer_id' => $reservation->customer_id,
                    'task_id' => $reservation->task_id,
                    'idempotency_key' => 'rsv-ful-'.$reservation->id.'-item-'.$item->id.'-'.$fulfillQty.'-'.Str::uuid(),
                    'reference' => $reservation->reservation_number,
                ], $actor);

                if ($custodyLocationId) {
                    $this->ledger->postTransaction([
                        'type' => StockTransaction::TYPE_RECEIPT,
                        'branch_id' => $reservation->branch_id,
                        'product_id' => $item->product_id,
                        'equipment_id' => $item->equipment_id,
                        'qty' => $fulfillQty,
                        'to_location_id' => $custodyLocationId,
                        'idempotency_key' => 'rsv-ful-cust-'.$reservation->id.'-item-'.$item->id.'-'.$fulfillQty.'-'.Str::uuid(),
                        'reference' => $reservation->reservation_number,
                    ], $actor);
                }

                $item->qty_fulfilled = (float) $item->qty_fulfilled + $fulfillQty;
                $item->save();

                if ((float) $item->qty_fulfilled < (float) $item->qty_reserved) {
                    $allDone = false;
                }
            }

            $reservation->status = $allDone
                ? Reservation::STATUS_FULFILLED
                : Reservation::STATUS_PARTIALLY_FULFILLED;
            $reservation->save();

            if ($allDone) {
                ReservationFulfilled::dispatch($reservation->id, (int) $reservation->branch_id);
            }

            return $reservation->fresh('items');
        });
    }

    public function expireDue(): int
    {
        $count = 0;
        Reservation::query()
            ->whereIn('status', [Reservation::STATUS_DRAFT, Reservation::STATUS_APPROVED, Reservation::STATUS_PENDING_APPROVAL])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (Reservation $r) use (&$count) {
                try {
                    if ((float) $r->items()->sum('qty_reserved') > 0) {
                        $this->release($r);
                    }
                    $r->status = Reservation::STATUS_EXPIRED;
                    $r->save();
                    $count++;
                } catch (\Throwable) {
                    // continue
                }
            });

        return $count;
    }
}
