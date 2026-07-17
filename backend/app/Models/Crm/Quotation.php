<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Quotation extends Model
{
    use SoftDeletes;

    protected $table = 'crm_quotations';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'quote_number',
        'lead_id',
        'branch_id',
        'monthly_package',
        'installation_fee',
        'discount',
        'equipment_json',
        'taxes',
        'notes',
        'valid_until',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_package' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'taxes' => 'decimal:2',
            'equipment_json' => 'array',
            'valid_until' => 'date',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
