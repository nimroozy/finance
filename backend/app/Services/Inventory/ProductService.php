<?php

namespace App\Services\Inventory;

use App\Events\Inventory\ZohoInventoryItemSyncRequested;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Product
    {
        $mode = $data['tracking_mode'] ?? Product::TRACK_QUANTITY;
        if (! in_array($mode, Product::TRACKING_MODES, true)) {
            throw new InvalidArgumentException('Invalid tracking_mode.');
        }

        return DB::transaction(function () use ($data, $mode) {
            $product = Product::query()->create([
                'code' => $data['code'] ?? Str::upper(Str::random(8)),
                'sku' => $data['sku'] ?? null,
                'name_en' => $data['name_en'],
                'name_fa' => $data['name_fa'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'uom' => $data['uom'] ?? 'ea',
                'barcode' => $data['barcode'] ?? null,
                'tracking_mode' => $mode,
                'is_purchase' => $data['is_purchase'] ?? true,
                'is_sales' => $data['is_sales'] ?? true,
                'is_installable' => $data['is_installable'] ?? false,
                'is_fixed_asset_eligible' => $data['is_fixed_asset_eligible'] ?? false,
                'is_consumable' => $data['is_consumable'] ?? false,
                'is_returnable' => $data['is_returnable'] ?? true,
                'is_company_owned' => $data['is_company_owned'] ?? true,
                'purchase_price' => $data['purchase_price'] ?? null,
                'sales_price' => $data['sales_price'] ?? null,
                'min_stock' => $data['min_stock'] ?? 0,
                'reorder_qty' => $data['reorder_qty'] ?? null,
                'warranty_months' => $data['warranty_months'] ?? null,
                'zoho_sync_status' => 'pending',
                'zoho_idempotency_key' => (string) Str::uuid(),
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('inventory.product.created', $product, null, $product->toArray());
            ZohoInventoryItemSyncRequested::dispatch($product->id);

            return $product;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $fields = [
                'sku', 'name_en', 'name_fa', 'category_id', 'brand', 'model', 'uom', 'barcode',
                'tracking_mode', 'is_purchase', 'is_sales', 'is_installable', 'is_fixed_asset_eligible',
                'is_consumable', 'is_returnable', 'is_company_owned', 'purchase_price', 'sales_price',
                'min_stock', 'reorder_qty', 'warranty_months', 'is_active', 'notes',
            ];
            $old = $product->only($fields);
            $product->fill(collect($data)->only($fields)->all());
            $product->save();
            $this->audit->log('inventory.product.updated', $product, $old, $product->only($fields));

            return $product->fresh();
        });
    }
}
