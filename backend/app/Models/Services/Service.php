<?php

namespace App\Models\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\Installation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Service extends Model
{
    use SoftDeletes;

    public const COMMERCIAL_DRAFT = 'draft';

    public const COMMERCIAL_PENDING_ACTIVATION = 'pending_activation';

    public const COMMERCIAL_ACTIVE = 'active';

    public const COMMERCIAL_SUSPENDED = 'suspended';

    public const COMMERCIAL_PENDING_CANCELLATION = 'pending_cancellation';

    public const COMMERCIAL_CANCELLED = 'cancelled';

    public const COMMERCIAL_EXPIRED = 'expired';

    public const COMMERCIAL_STATUSES = [
        self::COMMERCIAL_DRAFT,
        self::COMMERCIAL_PENDING_ACTIVATION,
        self::COMMERCIAL_ACTIVE,
        self::COMMERCIAL_SUSPENDED,
        self::COMMERCIAL_PENDING_CANCELLATION,
        self::COMMERCIAL_CANCELLED,
        self::COMMERCIAL_EXPIRED,
    ];

    public const OPERATIONAL_NOT_PROVISIONED = 'not_provisioned';

    public const OPERATIONAL_PENDING_INSTALL = 'pending_install';

    public const OPERATIONAL_READY = 'ready';

    public const OPERATIONAL_ONLINE = 'online';

    public const OPERATIONAL_OFFLINE = 'offline';

    public const OPERATIONAL_SUSPENDED = 'suspended';

    public const OPERATIONAL_DECOMMISSIONED = 'decommissioned';

    public const OPERATIONAL_STATUSES = [
        self::OPERATIONAL_NOT_PROVISIONED,
        self::OPERATIONAL_PENDING_INSTALL,
        self::OPERATIONAL_READY,
        self::OPERATIONAL_ONLINE,
        self::OPERATIONAL_OFFLINE,
        self::OPERATIONAL_SUSPENDED,
        self::OPERATIONAL_DECOMMISSIONED,
    ];

    public const BILLING_NOT_BILLED = 'not_billed';

    public const BILLING_CURRENT = 'current';

    public const BILLING_OVERDUE = 'overdue';

    public const BILLING_ON_HOLD = 'on_hold';

    public const BILLING_CLOSED = 'closed';

    public const BILLING_STATUSES = [
        self::BILLING_NOT_BILLED,
        self::BILLING_CURRENT,
        self::BILLING_OVERDUE,
        self::BILLING_ON_HOLD,
        self::BILLING_CLOSED,
    ];

    protected $fillable = [
        'service_number', 'customer_id', 'zoho_customer_id', 'branch_id', 'service_location_id',
        'installation_id', 'lead_id', 'opportunity_id', 'quotation_id',
        'package_id', 'package_version_id', 'service_type_code', 'access_technology_code',
        'billing_frequency', 'mrr', 'installation_fee', 'currency', 'contract_id',
        'start_date', 'activation_date', 'suspension_date', 'reactivation_date',
        'cancellation_date', 'expiration_date',
        'commercial_status', 'operational_status', 'billing_status', 'ownership_status',
        'technical_owner_id', 'sales_owner_id', 'support_owner_id',
        'tower_id', 'site_id', 'network_node', 'vlan', 'static_ip', 'public_ip', 'private_ip',
        'pppoe_username_placeholder', 'circuit_id', 'customer_reference',
        'sla_template_id', 'notes', 'metadata', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'mrr' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'start_date' => 'date',
            'activation_date' => 'datetime',
            'suspension_date' => 'datetime',
            'reactivation_date' => 'datetime',
            'cancellation_date' => 'datetime',
            'expiration_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedCommercialTransitions(): array
    {
        return [
            self::COMMERCIAL_DRAFT => [self::COMMERCIAL_PENDING_ACTIVATION, self::COMMERCIAL_CANCELLED],
            self::COMMERCIAL_PENDING_ACTIVATION => [self::COMMERCIAL_ACTIVE, self::COMMERCIAL_CANCELLED, self::COMMERCIAL_DRAFT],
            self::COMMERCIAL_ACTIVE => [
                self::COMMERCIAL_SUSPENDED,
                self::COMMERCIAL_PENDING_CANCELLATION,
                self::COMMERCIAL_EXPIRED,
            ],
            self::COMMERCIAL_SUSPENDED => [
                self::COMMERCIAL_ACTIVE,
                self::COMMERCIAL_PENDING_CANCELLATION,
                self::COMMERCIAL_CANCELLED,
                self::COMMERCIAL_EXPIRED,
            ],
            self::COMMERCIAL_PENDING_CANCELLATION => [self::COMMERCIAL_CANCELLED, self::COMMERCIAL_ACTIVE],
            self::COMMERCIAL_CANCELLED => [],
            self::COMMERCIAL_EXPIRED => [self::COMMERCIAL_ACTIVE, self::COMMERCIAL_CANCELLED],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedOperationalTransitions(): array
    {
        return [
            self::OPERATIONAL_NOT_PROVISIONED => [
                self::OPERATIONAL_PENDING_INSTALL,
                self::OPERATIONAL_READY,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_PENDING_INSTALL => [
                self::OPERATIONAL_READY,
                self::OPERATIONAL_ONLINE,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_READY => [
                self::OPERATIONAL_ONLINE,
                self::OPERATIONAL_OFFLINE,
                self::OPERATIONAL_SUSPENDED,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_ONLINE => [
                self::OPERATIONAL_OFFLINE,
                self::OPERATIONAL_SUSPENDED,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_OFFLINE => [
                self::OPERATIONAL_ONLINE,
                self::OPERATIONAL_SUSPENDED,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_SUSPENDED => [
                self::OPERATIONAL_ONLINE,
                self::OPERATIONAL_READY,
                self::OPERATIONAL_DECOMMISSIONED,
            ],
            self::OPERATIONAL_DECOMMISSIONED => [],
        ];
    }

    public function canTransitionCommercialTo(string $to): bool
    {
        $allowed = self::allowedCommercialTransitions()[$this->commercial_status] ?? [];

        return in_array($to, $allowed, true);
    }

    public function canTransitionOperationalTo(string $to): bool
    {
        $allowed = self::allowedOperationalTransitions()[$this->operational_status] ?? [];

        return in_array($to, $allowed, true);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class, 'service_location_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }

    public function packageVersion(): BelongsTo
    {
        return $this->belongsTo(ServicePackageVersion::class, 'package_version_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id');
    }

    public function slaTemplate(): BelongsTo
    {
        return $this->belongsTo(ServiceSlaTemplate::class, 'sla_template_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class, 'contract_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ServiceStatusTransition::class, 'service_id');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(ServiceActivation::class, 'service_id');
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(ServiceSuspension::class, 'service_id');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(ServiceCancellation::class, 'service_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ServiceChangeRequest::class, 'service_id');
    }

    public function relocations(): HasMany
    {
        return $this->hasMany(ServiceRelocation::class, 'service_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(ServiceRenewal::class, 'service_id');
    }

    public function financeHolds(): HasMany
    {
        return $this->hasMany(ServiceFinanceHold::class, 'service_id');
    }

    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_owner_id');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function supportOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_owner_id');
    }
}
