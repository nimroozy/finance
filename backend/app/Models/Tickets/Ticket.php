<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Org\Department;
use App\Models\Org\Team;
use App\Models\Payment;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppConversation;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Ticket extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const STATUS_WAITING_INTERNAL = 'waiting_internal';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_CUSTOMER,
        self::STATUS_WAITING_INTERNAL,
        self::STATUS_ESCALATED,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    public const PRIORITIES = [
        self::PRIORITY_CRITICAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
    ];

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    public const SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_HIGH,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_LOW,
    ];

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_PHONE = 'phone';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_EMAIL = 'email';

    public const SOURCES = [
        self::SOURCE_WHATSAPP,
        self::SOURCE_PHONE,
        self::SOURCE_WALK_IN,
        self::SOURCE_INTERNAL,
        self::SOURCE_SYSTEM,
        self::SOURCE_EMAIL,
    ];

    protected $fillable = [
        'ticket_number',
        'branch_id',
        'customer_id',
        'customer_number',
        'source',
        'type_code',
        'category',
        'subject',
        'description',
        'priority',
        'severity',
        'impact',
        'urgency',
        'status',
        'assigned_department_id',
        'assigned_team_id',
        'primary_assignee_id',
        'sla_policy_id',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'reopened_count',
        'customer_phone',
        'customer_location',
        'gps_lat',
        'gps_lng',
        'related_tower',
        'related_site',
        'related_radius_account',
        'related_invoice_id',
        'related_payment_id',
        'related_installation_id',
        'whatsapp_conversation_id',
        'external_reference',
        'tags',
        'internal_notes',
        'customer_visible_notes',
        'resolution_code',
        'resolution_summary',
        'customer_confirmation',
        'customer_confirmed_at',
        'major_incident_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'reopened_count' => 'integer',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'tags' => 'array',
            'customer_confirmation' => 'boolean',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_OPEN => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_WAITING_CUSTOMER,
                self::STATUS_WAITING_INTERNAL,
                self::STATUS_ESCALATED,
                self::STATUS_RESOLVED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_IN_PROGRESS => [
                self::STATUS_WAITING_CUSTOMER,
                self::STATUS_WAITING_INTERNAL,
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
            self::STATUS_WAITING_INTERNAL => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_ESCALATED,
                self::STATUS_RESOLVED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_ESCALATED => [
                self::STATUS_IN_PROGRESS,
                self::STATUS_WAITING_CUSTOMER,
                self::STATUS_WAITING_INTERNAL,
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
            ],
            self::STATUS_CANCELLED => [],
        ];
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $allowed = self::allowedTransitions()[$this->status] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team_id');
    }

    public function primaryAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_assignee_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    public function relatedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    public function relatedInstallation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'related_installation_id');
    }

    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function majorIncident(): BelongsTo
    {
        return $this->belongsTo(MajorIncident::class, 'major_incident_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_watchers')
            ->withTimestamps();
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TicketStatusTransition::class);
    }

    public function slaState(): HasOne
    {
        return $this->hasOne(TicketSlaState::class);
    }

    public function slaBreachEvents(): HasMany
    {
        return $this->hasMany(SlaBreachEvent::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(OperationalAttachment::class, 'attachable');
    }

    public function majorIncidents(): BelongsToMany
    {
        return $this->belongsToMany(MajorIncident::class, 'major_incident_ticket')
            ->withTimestamps();
    }
}
