<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Scopes\CollectorOwnedScope;
use Database\Factories\CustomerAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class, CollectorOwnedScope::class])]
class CustomerAssignment extends Model
{
    /** @use HasFactory<CustomerAssignmentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REASSIGNED = 'reassigned';

    public const STATUS_FULLY_RESOLVED = 'fully_resolved';

    /** Statuses that keep is_active = true. */
    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
    ];

    /** Statuses that force is_active = false. */
    public const INACTIVE_STATUSES = [
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
        self::STATUS_REASSIGNED,
        self::STATUS_FULLY_RESOLVED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'collector_id',
        'assigned_by',
        'status',
        'is_active',
        'is_primary',
        'priority',
        'assignment_source',
        'debt_snapshot_outstanding',
        'debt_snapshot_overdue',
        'debt_snapshot_invoice_count',
        'debt_snapshot_currency',
        'due_date',
        'planned_visit_date',
        'notes',
        'manager_notes',
        'collector_instructions',
        'assignment_reason',
        'oldest_due_date_snapshot',
        'days_overdue_snapshot',
        'allow_shared_assignment',
        'cancel_reason',
        'accepted_at',
        'first_viewed_at',
        'last_visit_at',
        'closed_at',
        'reassigned_from_id',
        'reassignment_count',
        'bulk_operation_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'allow_shared_assignment' => 'boolean',
            'debt_snapshot_outstanding' => 'decimal:4',
            'debt_snapshot_overdue' => 'decimal:4',
            'debt_snapshot_invoice_count' => 'integer',
            'days_overdue_snapshot' => 'integer',
            'reassignment_count' => 'integer',
            'due_date' => 'date',
            'planned_visit_date' => 'date',
            'oldest_due_date_snapshot' => 'date',
            'accepted_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function isActiveStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CustomerAssignmentHistory::class, 'assignment_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AssignmentComment::class, 'assignment_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CollectionVisit::class, 'assignment_id');
    }

    public function promises(): HasMany
    {
        return $this->hasMany(PromiseToPay::class, 'assignment_id');
    }

    public function bulkOperation(): BelongsTo
    {
        return $this->belongsTo(AssignmentBulkOperation::class, 'bulk_operation_id');
    }

    public function reassignedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reassigned_from_id');
    }
}
