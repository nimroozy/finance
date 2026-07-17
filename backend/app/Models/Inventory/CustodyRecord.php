<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class CustodyRecord extends Model
{
    protected $table = 'inventory_custody_records';

    protected $fillable = [
        'branch_id', 'user_id', 'product_id', 'equipment_id', 'from_location_id',
        'custody_location_id', 'qty', 'status', 'issued_at', 'returned_at', 'issued_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
