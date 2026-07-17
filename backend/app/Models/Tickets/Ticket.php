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

    public const STATUS_NEW = 'new';

    public const STATUS_TRIAGED = 'triaged';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';

    public const STATUS_WAITING_FINANCE = 'waiting_finance';

    public const STATUS_WAITING_NOC = 'waiting_noc';

    public const STATUS_WAITING_TECHNICAL = 'waiting_technical';

    public const STATUS_WAITING_EQUIPMENT = 'waiting_equipment';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_VERIFICATION_PENDING = 'verification_pending';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REOPENED = 'reopened';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_TRIAGED,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAITING_CUSTOMER,
        self::STATUS_WAITING_FINANCE,
        self::STATUS_WAITING_NOC,
        self::STATUS_WAITING_TECHNICAL,
        self::STATUS_WAITING_EQUIPMENT,
        self::STATUS_SCHEDULED,
        self::STATUS_ESCALATED,
        self::STATUS_RESOLVED,
        self::STATUS_VERIFICATION_PENDING,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
        self::STATUS_REOPENED,
    ];

    public const WAITING_STATUSES = [
        self::STATUS_WAITING_CUSTOMER,
        self::STATUS_WAITING_FINANCE,
        self::STATUS_WAITING_NOC,
        self::STATUS_WAITING_TECHNICAL,
        self::STATUS_WAITING_EQUIPMENT,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
        self::PRIORITY_CRITICAL,
    ];

    public const SEVERITY_INDIVIDUAL_CUSTOMER = 'individual_customer';

    public const SEVERITY_MULTIPLE_CUSTOMERS = 'multiple_customers';

    public const SEVERITY_NEIGHBORHOOD = 'neighborhood';

    public const SEVERITY_TOWER = 'tower';

    public const SEVERITY_BRANCH = 'branch';

    public const SEVERITY_NETWORK_WIDE = 'network_wide';

    public const SEVERITIES = [
        self::SEVERITY_INDIVIDUAL_CUSTOMER,
        self::SEVERITY_MULTIPLE_CUSTOMERS,
        self::SEVERITY_NEIGHBORHOOD,
        self::SEVERITY_TOWER,
        self::SEVERITY_BRANCH,
        self::SEVERITY_NETWORK_WIDE,
    ];

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_WHATSAPP = 'whatsapp';

    public const SOURCE_PHONE_CALL = 'phone_call';

    public const SOURCE_CUSTOMER_PORTAL = 'customer_portal';

    public const SOURCE_SALES = 'sales';

    public const SOURCE_FINANCE = 'finance';

    public const SOURCE_NOC = 'noc';

    public const SOURCE_TECHNICAL = 'technical';

    public const SOURCE_MONITORING = 'monitoring';

    public const SOURCE_INSTALLATION = 'installation';

    public const SOURCE_RADIUS = 'radius';

    public const SOURCE_EMAIL_FUTURE = 'email_future';

    public const SOURCE_API = 'api';

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_WHATSAPP,
        self::SOURCE_PHONE_CALL,
        self::SOURCE_CUSTOMER_PORTAL,
        self::SOURCE_SALES,
        self::SOURCE_FINANCE,
        self::SOURCE_NOC,
        self::SOURCE_TECHNICAL,
        self::SOURCE_MONITORING,
        self::SOURCE_INSTALLATION,
        self::SOURCE_RADIUS,
        self::SOURCE_EMAIL_FUTURE,
        self::SOURCE_API,
        self::SOURCE_INTERNAL,
    ];

    protected $fillable = [
        'ticket_number',
        'branch_id',
        'customer_id',
        'service_id',
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
        $waiting = self::WAITING_STATUSES;

        $fromInProgress = array_merge($waiting, [
            self::STATUS_SCHEDULED,
            self::STATUS_ESCALATED,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ]);

        $fromWaiting = [
            self::STATUS_IN_PROGRESS,
            self::STATUS_ASSIGNED,
            self::STATUS_ESCALATED,
            self::STATUS_RESOLVED,
            self::STATUS_CANCELLED,
        ];

        $map = [
            self::STATUS_NEW => [
                self::STATUS_TRIAGED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_TRIAGED => [
                self::STATUS_ASSIGNED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_ESCALATED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_ASSIGNED => array_merge([
                self::STATUS_IN_PROGRESS,
                self::STATUS_SCHEDULED,
                self::STATUS_ESCALATED,
                self::STATUS_CANCELLED,
            ], $waiting),
            self::STATUS_IN_PROGRESS => $fromInProgress,
            self::STATUS_SCHEDULED => array_merge([
                self::STATUS_IN_PROGRESS,
                self::STATUS_ASSIGNED,
                self::STATUS_ESCALATED,
                self::STATUS_CANCELLED,
            ], $waiting),
            self::STATUS_ESCALATED => array_merge([
                self::STATUS_IN_PROGRESS,
                self::STATUS_ASSIGNED,
                self::STATUS_RESOLVED,
                self::STATUS_CANCELLED,
            ], $waiting),
            self::STATUS_RESOLVED => [
                self::STATUS_VERIFICATION_PENDING,
                self::STATUS_CLOSED,
                self::STATUS_REOPENED,
            ],
            self::STATUS_VERIFICATION_PENDING => [
                self::STATUS_CLOSED,
                self::STATUS_IN_PROGRESS,
                self::STATUS_REOPENED,
            ],
            self::STATUS_CLOSED => [
                self::STATUS_REOPENED,
            ],
            self::STATUS_REOPENED => [
                self::STATUS_TRIAGED,
                self::STATUS_ASSIGNED,
                self::STATUS_IN_PROGRESS,
            ],
            self::STATUS_CANCELLED => [],
        ];

        foreach ($waiting as $status) {
            $map[$status] = $fromWaiting;
        }

        return $map;
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $allowed = self::allowedTransitions()[$this->status] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'type_code', 'code');
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

    public function transitions(): HasMany
    {
        return $this->statusTransitions();
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
