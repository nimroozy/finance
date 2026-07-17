<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class FixedAsset extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_fixed_assets';

    protected $fillable = [
        'asset_number', 'branch_id', 'product_id', 'equipment_id', 'name', 'category',
        'location_id', 'site_id', 'tower_id', 'custodian_user_id', 'acquisition_cost',
        'acquisition_date', 'condition', 'status', 'warranty_end', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_cost' => 'decimal:2',
            'acquisition_date' => 'date',
            'warranty_end' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }
}
