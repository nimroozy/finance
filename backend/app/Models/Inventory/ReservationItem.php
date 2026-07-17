<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationItem extends Model
{
    protected $table = 'inventory_reservation_items';

    protected $fillable = [
        'reservation_id', 'product_id', 'equipment_id',
        'qty_requested', 'qty_reserved', 'qty_fulfilled', 'qty_released',
    ];

    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:3',
            'qty_reserved' => 'decimal:3',
            'qty_fulfilled' => 'decimal:3',
            'qty_released' => 'decimal:3',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
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
