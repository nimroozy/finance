<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    protected $table = 'inventory_transfer_items';

    protected $fillable = [
        'transfer_id', 'product_id', 'equipment_id',
        'qty_requested', 'qty_dispatched', 'qty_received', 'discrepancy_notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:3',
            'qty_dispatched' => 'decimal:3',
            'qty_received' => 'decimal:3',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
