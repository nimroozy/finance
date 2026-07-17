<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Site extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_sites';

    protected $fillable = [
        'code', 'branch_id', 'name', 'address', 'gps_lat', 'gps_lng', 'site_type',
        'landlord', 'lease_start', 'lease_end', 'rent', 'contact_name', 'contact_phone',
        'power_source', 'power_capacity', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'lease_start' => 'date',
            'lease_end' => 'date',
            'rent' => 'decimal:2',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function towers(): HasMany
    {
        return $this->hasMany(Tower::class, 'site_id');
    }
}
