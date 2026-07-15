<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Scopes\CollectorOwnedScope;
use Database\Factories\PromiseToPayFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class, CollectorOwnedScope::class])]
class PromiseToPay extends Model
{
    /** @use HasFactory<PromiseToPayFactory> */
    use HasFactory;

    protected $table = 'promise_to_pay';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DUE_SOON = 'due_soon';

    public const STATUS_DUE_TODAY = 'due_today';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_SUPERSEDED = 'superseded';

    public const OPEN_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DUE_SOON,
        self::STATUS_DUE_TODAY,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'branch_id',
        'customer_id',
        'assignment_id',
        'visit_id',
        'collector_id',
        'created_by',
        'amount',
        'currency',
        'promised_date',
        'status',
        'notes',
        'fulfilled_at',
        'fulfilled_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'superseded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'promised_date' => 'date',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'visit_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PromiseToPayStatusHistory::class, 'promise_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
