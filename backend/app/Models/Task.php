<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_TRAVELING = 'traveling';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_CANCELLED = 'cancelled';

    public const WORK_OFFICE = 'office';
    public const WORK_FIELD = 'field';

    protected $fillable = [
        'uuid', 'branch_id', 'number', 'ticket_id', 'installation_id',
        'department_id', 'team_id', 'assignee_id', 'offered_to_id', 'created_by',
        'title', 'description', 'status', 'priority', 'work_type', 'checklist',
        'block_reason', 'cancel_reason', 'reject_reason',
        'offered_at', 'accepted_at', 'started_at', 'traveled_at', 'arrived_at',
        'completed_at', 'verified_at', 'verified_by', 'due_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'traveled_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'due_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_task_id', 'task_id');
    }

    public function assignmentEvents(): HasMany
    {
        return $this->hasMany(TaskAssignmentEvent::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_VERIFIED, self::STATUS_CANCELLED], true);
    }

    public function isCompleteForDependency(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VERIFIED], true);
    }
}
