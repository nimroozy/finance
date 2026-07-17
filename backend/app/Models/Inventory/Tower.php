<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class Tower extends Model
{
    protected $table = 'inventory_towers';

    protected $fillable = [
        'code', 'site_id', 'branch_id', 'height_m', 'structure_type', 'owner',
        'operational_status', 'installation_date', 'last_inspection_date',
        'next_inspection_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'height_m' => 'decimal:2',
            'installation_date' => 'date',
            'last_inspection_date' => 'date',
            'next_inspection_date' => 'date',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
