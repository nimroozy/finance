<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_WHATSAPP = 'whatsapp';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_WAITING_EQUIPMENT = 'waiting_equipment';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ESCALATED = 'escalated';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, list<string>> */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_OPEN => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_CUSTOMER,
            self::STATUS_WAITING_EQUIPMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_ESCALATED,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_IN_PROGRESS => [
            self::STATUS_WAITING_CUSTOMER,
            self::STATUS_WAITING_EQUIPMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_ESCALATED,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_WAITING_CUSTOMER => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_ESCALATED,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_WAITING_EQUIPMENT => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_SCHEDULED,
            self::STATUS_ESCALATED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_SCHEDULED => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_CUSTOMER,
            self::STATUS_WAITING_EQUIPMENT,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ESCALATED => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_CUSTOMER,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_RESOLVED => [
            self::STATUS_CLOSED,
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
        ],
        self::STATUS_CLOSED => [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
        ],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'uuid', 'branch_id', 'number', 'type', 'channel', 'status', 'priority',
        'category', 'subcategory', 'subject', 'description',
        'customer_id', 'conversation_id', 'department_id', 'team_id',
        'reporter_id', 'assignee_id', 'sla_policy_id',
        'first_response_due_at', 'resolution_due_at', 'first_responded_at',
        'breached_at', 'sla_paused', 'sla_paused_at', 'sla_paused_seconds',
        'major_incident_id', 'resolution_notes', 'customer_confirmed',
        'resolved_at', 'closed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'breached_at' => 'datetime',
            'sla_paused' => 'boolean',
            'sla_paused_at' => 'datetime',
            'sla_paused_seconds' => 'integer',
            'customer_confirmed' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function majorIncident(): BelongsTo
    {
        return $this->belongsTo(self::class, 'major_incident_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(TicketStatusTransition::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_watchers')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_CLOSED, self::STATUS_CANCELLED, self::STATUS_RESOLVED], true);
    }
}
