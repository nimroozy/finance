<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Org\Department;
use App\Models\Org\Team;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Task extends Model
{
    use SoftDeletes;

    public const TYPE_FIELD = 'field';

    public const TYPE_OFFICE = 'office';

    public const STATUS_PENDING = 'pending';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_TRAVELLING = 'travelling';

    public const STATUS_ARRIVED = 'arrived';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VERIFICATION_PENDING = 'verification_pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_OFFERED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_SCHEDULED,
        self::STATUS_TRAVELLING,
        self::STATUS_ARRIVED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_WAITING,
        self::STATUS_COMPLETED,
        self::STATUS_VERIFICATION_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_LOW = 'low';

    public const PRIORITIES = [
        self::PRIORITY_CRITICAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_NORMAL,
        self::PRIORITY_LOW,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    public const COMPLETE_FOR_DEPENDENCY = [
        self::STATUS_COMPLETED,
        self::STATUS_VERIFICATION_PENDING,
        self::STATUS_APPROVED,
    ];

    protected $fillable = [
        'task_number',
        'branch_id',
        'department_id',
        'team_id',
        'assignee_id',
        'created_by',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'started_at',
        'completed_at',
        'due_at',
        'location',
        'gps_lat',
        'gps_lng',
        'customer_id',
        'ticket_id',
        'installation_id',
        'parent_task_id',
        'checklist',
        'required_evidence',
        'completion_notes',
        'requires_approval',
        'approver_id',
        'escalation_state',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'checklist' => 'array',
            'required_evidence' => 'array',
            'requires_approval' => 'boolean',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_PENDING => [
                self::STATUS_OFFERED,
                self::STATUS_SCHEDULED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_OFFERED => [
                self::STATUS_ACCEPTED,
                self::STATUS_REJECTED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_ACCEPTED => [
                self::STATUS_SCHEDULED,
                self::STATUS_TRAVELLING,
                self::STATUS_ARRIVED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_BLOCKED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_REJECTED => [
                self::STATUS_OFFERED,
                self::STATUS_PENDING,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_SCHEDULED => [
                self::STATUS_TRAVELLING,
                self::STATUS_ARRIVED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_OFFERED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_TRAVELLING => [
                self::STATUS_ARRIVED,
                self::STATUS_BLOCKED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_ARRIVED => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_BLOCKED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_IN_PROGRESS => [
                self::STATUS_BLOCKED,
                self::STATUS_WAITING,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED,
            ],
            self::STATUS_BLOCKED => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_WAITING,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_WAITING => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_BLOCKED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_COMPLETED => [
                self::STATUS_VERIFICATION_PENDING,
                self::STATUS_APPROVED,
            ],
            self::STATUS_VERIFICATION_PENDING => [
                self::STATUS_APPROVED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_FAILED,
            ],
            self::STATUS_APPROVED => [],
            self::STATUS_CANCELLED => [],
            self::STATUS_FAILED => [
                self::STATUS_OFFERED,
                self::STATUS_PENDING,
                self::STATUS_CANCELLED,
            ],
        ];
    }

    /**
     * Field-worker oriented transitions.
     *
     * @return array<string, list<string>>
     */
    public static function fieldAllowedTransitions(): array
    {
        return [
            self::STATUS_OFFERED => [self::STATUS_ACCEPTED, self::STATUS_REJECTED],
            self::STATUS_ACCEPTED => [self::STATUS_TRAVELLING, self::STATUS_ARRIVED, self::STATUS_IN_PROGRESS],
            self::STATUS_TRAVELLING => [self::STATUS_ARRIVED, self::STATUS_BLOCKED],
            self::STATUS_ARRIVED => [self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_BLOCKED, self::STATUS_WAITING],
            self::STATUS_BLOCKED => [self::STATUS_IN_PROGRESS],
            self::STATUS_WAITING => [self::STATUS_IN_PROGRESS],
            self::STATUS_COMPLETED => [self::STATUS_VERIFICATION_PENDING],
            self::STATUS_VERIFICATION_PENDING => [self::STATUS_APPROVED],
        ];
    }

    /**
     * Office / dispatcher oriented transitions.
     *
     * @return array<string, list<string>>
     */
    public static function officeAllowedTransitions(): array
    {
        return [
            self::STATUS_PENDING => [self::STATUS_IN_PROGRESS, self::STATUS_OFFERED, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_BLOCKED, self::STATUS_WAITING, self::STATUS_CANCELLED],
            self::STATUS_BLOCKED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_WAITING => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_VERIFICATION_PENDING, self::STATUS_APPROVED],
            self::STATUS_VERIFICATION_PENDING => [self::STATUS_APPROVED, self::STATUS_IN_PROGRESS],
        ];
    }

    public function canTransitionTo(string $toStatus, string $context = 'all'): bool
    {
        $map = match ($context) {
            'field' => self::fieldAllowedTransitions(),
            'office' => self::officeAllowedTransitions(),
            default => self::allowedTransitions(),
        };

        $allowed = $map[$this->status] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isCompleteForDependency(): bool
    {
        return in_array($this->status, self::COMPLETE_FOR_DEPENDENCY, true);
    }

    public function isField(): bool
    {
        return $this->type === self::TYPE_FIELD;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function childTasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function dependsOnTasks(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'task_dependencies',
            'task_id',
            'depends_on_task_id'
        )->withPivot('type')->withTimestamps();
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TaskStatusTransition::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(OperationalAttachment::class, 'attachable');
    }
}
