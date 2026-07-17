<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Equipment;
use App\Models\Inventory\Product;
use App\Models\Inventory\Site;
use App\Models\Inventory\Tower;

class InventorySearchService
{
    public function search(string $term, ?int $branchId = null, int $limit = 20): array
    {
        $like = '%'.$term.'%';

        return [
            'products' => Product::query()
                ->where(function ($q) use ($like) {
                    $q->where('code', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('name_en', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'code', 'sku', 'name_en', 'tracking_mode']),
            'equipment' => Equipment::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($like) {
                    $q->where('equipment_number', 'like', $like)
                        ->orWhere('serial_number', 'like', $like)
                        ->orWhere('mac_address', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'equipment_number', 'serial_number', 'status', 'product_id']),
            'sites' => Site::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($like) {
                    $q->where('code', 'like', $like)->orWhere('name', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'code', 'name', 'status']),
            'towers' => Tower::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($like) {
                    $q->where('code', 'like', $like)->orWhere('owner', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'code', 'site_id', 'operational_status']),
        ];
    }
}
