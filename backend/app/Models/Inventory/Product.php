<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_products';

    public const TRACK_SERIALIZED = 'serialized';

    public const TRACK_QUANTITY = 'quantity';

    public const TRACK_BATCH = 'batch';

    public const TRACK_NON_STOCK = 'non_stock_service';

    public const TRACKING_MODES = [
        self::TRACK_SERIALIZED,
        self::TRACK_QUANTITY,
        self::TRACK_BATCH,
        self::TRACK_NON_STOCK,
    ];

    protected $fillable = [
        'code', 'sku', 'name_en', 'name_fa', 'category_id', 'brand', 'model', 'uom', 'barcode',
        'tracking_mode', 'is_purchase', 'is_sales', 'is_installable', 'is_fixed_asset_eligible',
        'is_consumable', 'is_returnable', 'is_company_owned', 'purchase_price', 'sales_price',
        'min_stock', 'reorder_qty', 'warranty_months', 'zoho_item_id', 'zoho_sync_status',
        'zoho_idempotency_key', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_purchase' => 'boolean',
            'is_sales' => 'boolean',
            'is_installable' => 'boolean',
            'is_fixed_asset_eligible' => 'boolean',
            'is_consumable' => 'boolean',
            'is_returnable' => 'boolean',
            'is_company_owned' => 'boolean',
            'is_active' => 'boolean',
            'purchase_price' => 'decimal:2',
            'sales_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'product_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'product_id');
    }

    public function isSerialized(): bool
    {
        return $this->tracking_mode === self::TRACK_SERIALIZED;
    }
}
