<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class AssignmentBulkOperation extends Model
{
    public const STATUS_PREVIEW = 'preview';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STRATEGY_MANUAL = 'manual';

    public const STRATEGY_ROUND_ROBIN = 'round_robin';

    public const STRATEGY_LEAST_LOADED = 'least_loaded';

    protected $fillable = [
        'branch_id',
        'created_by',
        'strategy',
        'status',
        'preview_payload',
        'result_payload',
        'selected_count',
        'total_outstanding',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'preview_payload' => 'array',
            'result_payload' => 'array',
            'selected_count' => 'integer',
            'total_outstanding' => 'decimal:4',
            'confirmed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerAssignment::class, 'bulk_operation_id');
    }
}
