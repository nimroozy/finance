<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Equipment;
use App\Models\Inventory\Product;
use App\Models\Inventory\Reservation;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Transfer;

class InventoryDashboardService
{
    public function summary(?int $branchId = null): array
    {
        $balances = StockBalance::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $onHand = (clone $balances)->sum('on_hand');
        $reserved = (clone $balances)->sum('reserved');
        $inTransit = (clone $balances)->sum('in_transit');

        $lowStock = Product::query()
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->get()
            ->filter(function (Product $p) use ($branchId) {
                $qty = StockBalance::query()
                    ->where('product_id', $p->id)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->sum('on_hand');

                return (float) $qty < (float) $p->min_stock;
            })
            ->count();

        return [
            'on_hand' => (float) $onHand,
            'reserved' => (float) $reserved,
            'available' => (float) $onHand - (float) $reserved,
            'in_transit' => (float) $inTransit,
            'low_stock_products' => $lowStock,
            'open_reservations' => Reservation::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereIn('status', [Reservation::STATUS_APPROVED, Reservation::STATUS_PARTIALLY_FULFILLED])
                ->count(),
            'open_transfers' => Transfer::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereIn('status', [Transfer::STATUS_APPROVED, Transfer::STATUS_IN_TRANSIT, Transfer::STATUS_DISPATCHED])
                ->count(),
            'equipment_in_stock' => Equipment::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', Equipment::STATUS_IN_STOCK)
                ->count(),
            'equipment_installed' => Equipment::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', Equipment::STATUS_INSTALLED)
                ->count(),
        ];
    }
}
