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

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SURVEY_SCHEDULED = 'survey_scheduled';

    public const STATUS_SURVEY_DONE = 'survey_done';

    public const STATUS_AWAITING_FINANCE = 'awaiting_finance';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EQUIPMENT_RESERVED = 'equipment_reserved';

    public const STATUS_IN_INSTALL = 'in_install';

    public const STATUS_AWAITING_ACTIVATION = 'awaiting_activation';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CANCELLED = 'cancelled';

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
