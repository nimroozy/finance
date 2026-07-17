<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $table = 'inventory_purchase_request_items';

    protected $fillable = ['purchase_request_id', 'product_id', 'qty', 'notes'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
