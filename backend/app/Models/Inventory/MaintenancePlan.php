<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\Task;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class MaintenancePlan extends Model
{
    protected $table = 'inventory_maintenance_plans';

    protected $fillable = [
        'branch_id', 'name', 'equipment_id', 'fixed_asset_id', 'tower_id', 'site_id',
        'frequency', 'next_due_date', 'is_active', 'last_task_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'next_due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function lastTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'last_task_id');
    }
}
