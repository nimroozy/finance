<?php

namespace App\Services\Inventory;

use App\Events\BusinessNotificationRequested;
use App\Models\Inventory\AlertRule;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockBalance;

class LowStockAlertService
{
    /**
     * Evaluate low-stock rules and emit digest-friendly business notification.
     *
     * @return list<array{product_id:int, code:string, on_hand:float, min_stock:float, branch_id:?int}>
     */
    public function evaluate(?int $branchId = null): array
    {
        $alerts = [];

        $products = Product::query()
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->get();

        foreach ($products as $product) {
            $onHand = (float) StockBalance::query()
                ->where('product_id', $product->id)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('on_hand');

            $threshold = (float) $product->min_stock;
            $rule = AlertRule::query()
                ->where('is_active', true)
                ->where('rule_type', 'low_stock')
                ->where(function ($q) use ($product) {
                    $q->where('product_id', $product->id)->orWhereNull('product_id');
                })
                ->when($branchId, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
                ->first();

            if ($rule && $rule->threshold !== null) {
                $threshold = (float) $rule->threshold;
            }

            if ($onHand < $threshold) {
                $alerts[] = [
                    'product_id' => $product->id,
                    'code' => $product->code,
                    'name_en' => $product->name_en,
                    'on_hand' => $onHand,
                    'min_stock' => $threshold,
                    'branch_id' => $branchId,
                ];
            }
        }

        if ($alerts !== []) {
            BusinessNotificationRequested::dispatch('inventory.low_stock_digest', [
                'count' => count($alerts),
                'items' => array_slice($alerts, 0, 50),
                'branch_id' => $branchId,
            ], ['in_app']);
        }

        return $alerts;
    }
}
