<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class StockBalance extends Model
{
    protected $table = 'inventory_stock_balances';

    protected $fillable = [
        'branch_id', 'location_id', 'product_id',
        'on_hand', 'reserved', 'in_transit', 'damaged', 'quarantine',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:3',
            'reserved' => 'decimal:3',
            'in_transit' => 'decimal:3',
            'damaged' => 'decimal:3',
            'quarantine' => 'decimal:3',
        ];
    }

    public function available(): float
    {
        return (float) $this->on_hand - (float) $this->reserved;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
