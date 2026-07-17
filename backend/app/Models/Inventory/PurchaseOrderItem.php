<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'inventory_purchase_order_items';

    protected $fillable = ['purchase_order_id', 'product_id', 'qty_ordered', 'qty_received', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:3',
            'qty_received' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
