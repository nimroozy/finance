<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Scopes\CollectorOwnedScope;
use Database\Factories\CollectionRouteFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class, CollectorOwnedScope::class])]
class CollectionRoute extends Model
{
    /** @use HasFactory<CollectionRouteFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'branch_id',
        'collector_id',
        'created_by',
        'name',
        'route_date',
        'status',
        'notes',
        'published_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'route_date' => 'date',
            'published_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(CollectionRouteStop::class, 'route_id')->orderBy('sequence');
    }
}
