<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRule extends Model
{
    protected $table = 'inventory_alert_rules';

    protected $fillable = [
        'branch_id', 'product_id', 'rule_type', 'threshold', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
