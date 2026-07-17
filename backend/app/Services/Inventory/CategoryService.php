<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ProductCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): ProductCategory
    {
        return DB::transaction(function () use ($data) {
            $cat = ProductCategory::query()->create([
                'parent_id' => $data['parent_id'] ?? null,
                'code' => $data['code'],
                'name_en' => $data['name_en'],
                'name_fa' => $data['name_fa'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->audit->log('inventory.category.created', $cat, null, $cat->toArray());

            return $cat;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(ProductCategory $category, array $data, ?User $actor = null): ProductCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $old = $category->toArray();
            $category->fill(collect($data)->only(['parent_id', 'code', 'name_en', 'name_fa', 'is_active'])->all());
            $category->save();
            $this->audit->log('inventory.category.updated', $category, $old, $category->toArray());

            return $category->fresh();
        });
    }
}
