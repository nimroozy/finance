<?php

namespace App\Models\Crm;

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
class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'crm_leads';

    public const STAGE_LEAD = 'lead';

    public const STAGE_CONTACTED = 'contacted';

    public const STAGE_QUALIFIED = 'qualified';

    public const STAGE_COVERAGE_CHECK = 'coverage_check';

    public const STAGE_SITE_SURVEY = 'site_survey';

    public const STAGE_QUOTATION = 'quotation';

    public const STAGE_NEGOTIATION = 'negotiation';

    public const STAGE_APPROVED = 'approved';

    public const STAGE_FINANCE_REVIEW = 'finance_review';

    public const STAGE_INSTALLATION_REQUEST = 'installation_request';

    public const STAGE_INSTALLATION_SCHEDULED = 'installation_scheduled';

    public const STAGE_INSTALLATION_COMPLETED = 'installation_completed';

    public const STAGE_RADIUS_ACTIVATION = 'radius_activation';

    public const STAGE_ZOHO_CUSTOMER = 'zoho_customer';

    public const STAGE_ACTIVE_CUSTOMER = 'active_customer';

    public const STAGE_AFTER_SALES = 'after_sales';

    public const STAGE_LOST = 'lost';

    public const STAGE_CANCELLED = 'cancelled';

    public const PIPELINE_STAGES = [
        self::STAGE_LEAD,
        self::STAGE_CONTACTED,
        self::STAGE_QUALIFIED,
        self::STAGE_COVERAGE_CHECK,
        self::STAGE_SITE_SURVEY,
        self::STAGE_QUOTATION,
        self::STAGE_NEGOTIATION,
        self::STAGE_APPROVED,
        self::STAGE_FINANCE_REVIEW,
        self::STAGE_INSTALLATION_REQUEST,
        self::STAGE_INSTALLATION_SCHEDULED,
        self::STAGE_INSTALLATION_COMPLETED,
        self::STAGE_RADIUS_ACTIVATION,
        self::STAGE_ZOHO_CUSTOMER,
        self::STAGE_ACTIVE_CUSTOMER,
        self::STAGE_AFTER_SALES,
        self::STAGE_LOST,
        self::STAGE_CANCELLED,
    ];

    public const CUSTOMER_TYPES = [
        'residential',
        'business',
        'enterprise',
        'government',
    ];

    protected $fillable = [
        'lead_number',
        'branch_id',
        'organization_id',
        'contact_id',
        'company',
        'contact_person',
        'phone',
        'whatsapp',
        'email',
        'address',
        'gps_lat',
        'gps_lng',
        'source_code',
        'campaign',
        'referral',
        'salesperson_id',
        'lead_value',
        'estimated_mrr',
        'expected_installation_fee',
        'interested_package',
        'customer_type',
        'pipeline_stage',
        'owner_id',
        'next_follow_up_at',
        'probability',
        'expected_close_date',
        'competitor',
        'lost_reason',
        'win_reason',
        'converted_customer_id',
        'installation_id',
        'opportunity_value',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'lead_value' => 'decimal:2',
            'estimated_mrr' => 'decimal:2',
            'expected_installation_fee' => 'decimal:2',
            'opportunity_value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'expected_close_date' => 'date',
            'probability' => 'integer',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        $lostOrCancel = [self::STAGE_LOST, self::STAGE_CANCELLED];

        return [
            self::STAGE_LEAD => array_merge([self::STAGE_CONTACTED, self::STAGE_QUALIFIED], $lostOrCancel),
            self::STAGE_CONTACTED => array_merge([self::STAGE_QUALIFIED, self::STAGE_COVERAGE_CHECK], $lostOrCancel),
            self::STAGE_QUALIFIED => array_merge([
                self::STAGE_COVERAGE_CHECK,
                self::STAGE_SITE_SURVEY,
                self::STAGE_QUOTATION,
            ], $lostOrCancel),
            self::STAGE_COVERAGE_CHECK => array_merge([
                self::STAGE_SITE_SURVEY,
                self::STAGE_QUOTATION,
            ], $lostOrCancel),
            self::STAGE_SITE_SURVEY => array_merge([
                self::STAGE_QUOTATION,
                self::STAGE_NEGOTIATION,
            ], $lostOrCancel),
            self::STAGE_QUOTATION => array_merge([
                self::STAGE_NEGOTIATION,
                self::STAGE_APPROVED,
            ], $lostOrCancel),
            self::STAGE_NEGOTIATION => array_merge([
                self::STAGE_QUOTATION,
                self::STAGE_APPROVED,
                self::STAGE_FINANCE_REVIEW,
            ], $lostOrCancel),
            self::STAGE_APPROVED => array_merge([
                self::STAGE_FINANCE_REVIEW,
                self::STAGE_INSTALLATION_REQUEST,
            ], $lostOrCancel),
            self::STAGE_FINANCE_REVIEW => array_merge([
                self::STAGE_APPROVED,
                self::STAGE_INSTALLATION_REQUEST,
            ], $lostOrCancel),
            self::STAGE_INSTALLATION_REQUEST => array_merge([
                self::STAGE_INSTALLATION_SCHEDULED,
            ], $lostOrCancel),
            self::STAGE_INSTALLATION_SCHEDULED => array_merge([
                self::STAGE_INSTALLATION_COMPLETED,
            ], $lostOrCancel),
            self::STAGE_INSTALLATION_COMPLETED => array_merge([
                self::STAGE_RADIUS_ACTIVATION,
                self::STAGE_ZOHO_CUSTOMER,
            ], $lostOrCancel),
            self::STAGE_RADIUS_ACTIVATION => array_merge([
                self::STAGE_ZOHO_CUSTOMER,
                self::STAGE_ACTIVE_CUSTOMER,
            ], $lostOrCancel),
            self::STAGE_ZOHO_CUSTOMER => array_merge([
                self::STAGE_ACTIVE_CUSTOMER,
                self::STAGE_AFTER_SALES,
            ], $lostOrCancel),
            self::STAGE_ACTIVE_CUSTOMER => [self::STAGE_AFTER_SALES],
            self::STAGE_AFTER_SALES => [],
            self::STAGE_LOST => [],
            self::STAGE_CANCELLED => [],
        ];
    }

    public function canTransitionTo(string $toStage): bool
    {
        $allowed = self::allowedTransitions()[$this->pipeline_stage] ?? [];

        return in_array($toStage, $allowed, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->pipeline_stage, [
            self::STAGE_LOST,
            self::STAGE_CANCELLED,
            self::STAGE_AFTER_SALES,
        ], true);
    }

    public function getStatusAttribute(): ?string
    {
        return $this->pipeline_stage;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_code', 'code');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id');
    }

    public function stageTransitions(): HasMany
    {
        return $this->hasMany(LeadStageTransition::class, 'lead_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'lead_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'lead_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'lead_id');
    }

    public function coverageChecks(): HasMany
    {
        return $this->hasMany(CoverageCheck::class, 'lead_id');
    }

    public function siteSurveys(): HasMany
    {
        return $this->hasMany(SiteSurvey::class, 'lead_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'lead_id');
    }
}
