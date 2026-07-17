<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppConversation;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Installation extends Model
{
    use SoftDeletes;

    public const STATUS_REQUEST_RECEIVED = 'request_received';

    public const STATUS_CUSTOMER_VERIFICATION = 'customer_verification';

    public const STATUS_COVERAGE_CHECK = 'coverage_check';

    public const STATUS_SITE_SURVEY_PENDING = 'site_survey_pending';

    public const STATUS_FINANCE_REVIEW = 'finance_review';

    public const STATUS_EQUIPMENT_WAITING = 'equipment_waiting';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_TRAVELLING = 'travelling';

    public const STATUS_INSTALLING = 'installing';

    public const STATUS_NOC_ACTIVATION_PENDING = 'noc_activation_pending';

    public const STATUS_CUSTOMER_CONFIRMATION = 'customer_confirmation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DELAYED = 'delayed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUEST_RECEIVED,
        self::STATUS_CUSTOMER_VERIFICATION,
        self::STATUS_COVERAGE_CHECK,
        self::STATUS_SITE_SURVEY_PENDING,
        self::STATUS_FINANCE_REVIEW,
        self::STATUS_EQUIPMENT_WAITING,
        self::STATUS_SCHEDULED,
        self::STATUS_ASSIGNED,
        self::STATUS_TRAVELLING,
        self::STATUS_INSTALLING,
        self::STATUS_NOC_ACTIVATION_PENDING,
        self::STATUS_CUSTOMER_CONFIRMATION,
        self::STATUS_COMPLETED,
        self::STATUS_DELAYED,
        self::STATUS_CANCELLED,
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_REQUEST_RECEIVED => [
                self::STATUS_CUSTOMER_VERIFICATION,
                self::STATUS_COVERAGE_CHECK,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_CUSTOMER_VERIFICATION => [
                self::STATUS_COVERAGE_CHECK,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_COVERAGE_CHECK => [
                self::STATUS_SITE_SURVEY_PENDING,
                self::STATUS_FINANCE_REVIEW,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_SITE_SURVEY_PENDING => [
                self::STATUS_FINANCE_REVIEW,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_FINANCE_REVIEW => [
                self::STATUS_EQUIPMENT_WAITING,
                self::STATUS_SCHEDULED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_EQUIPMENT_WAITING => [
                self::STATUS_SCHEDULED,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_SCHEDULED => [
                self::STATUS_ASSIGNED,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_ASSIGNED => [
                self::STATUS_TRAVELLING,
                self::STATUS_INSTALLING,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_TRAVELLING => [
                self::STATUS_INSTALLING,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_INSTALLING => [
                self::STATUS_NOC_ACTIVATION_PENDING,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_NOC_ACTIVATION_PENDING => [
                self::STATUS_CUSTOMER_CONFIRMATION,
                self::STATUS_DELAYED,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_CUSTOMER_CONFIRMATION => [
                self::STATUS_COMPLETED,
                self::STATUS_DELAYED,
            ],
            self::STATUS_DELAYED => [
                self::STATUS_SITE_SURVEY_PENDING,
                self::STATUS_EQUIPMENT_WAITING,
                self::STATUS_SCHEDULED,
                self::STATUS_ASSIGNED,
                self::STATUS_TRAVELLING,
                self::STATUS_INSTALLING,
                self::STATUS_NOC_ACTIVATION_PENDING,
                self::STATUS_CANCELLED,
            ],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $allowed = self::allowedTransitions()[$this->status] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    protected $fillable = [
        'installation_number',
        'branch_id',
        'status',
        'customer_id',
        'prospect_name',
        'contact_name',
        'phone',
        'address',
        'gps_lat',
        'gps_lng',
        'requested_package',
        'requested_date',
        'coverage_notes',
        'tower_site_candidate',
        'salesperson_id',
        'technician_id',
        'finance_reviewer_id',
        'noc_reviewer_id',
        'equipment_status',
        'installation_fee',
        'monthly_fee_estimate',
        'notes',
        'whatsapp_conversation_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'requested_date' => 'date',
            'installation_fee' => 'decimal:2',
            'monthly_fee_estimate' => 'decimal:2',
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

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function financeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewer_id');
    }

    public function nocReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noc_reviewer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'related_installation_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(OperationalAttachment::class, 'attachable');
    }
}
