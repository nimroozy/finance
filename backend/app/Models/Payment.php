<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Scopes\CollectorOwnedScope;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class, CollectorOwnedScope::class])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED_LOCAL = 'confirmed_local';

    public const STATUS_PENDING_ZOHO_SYNC = 'pending_zoho_sync';

    public const STATUS_SETTLED_PENDING_HANDOVER = 'settled_pending_handover';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_SYNC_FAILED = 'sync_failed';

    public const STATUS_REVERSED = 'reversed';

    public const STATUS_EXPIRED = 'expired';

    public const ZOHO_PENDING = 'pending';

    public const ZOHO_SYNCING = 'syncing';

    public const ZOHO_SYNCED = 'synced';

    public const ZOHO_FAILED = 'failed';

    public const ZOHO_DRY_RUN = 'dry_run';

    public const ZOHO_SKIPPED = 'skipped';

    /** Confirmed local statuses that reduce invoice effective available balance. */
    public const CONFIRMED_STATUSES = [
        self::STATUS_CONFIRMED_LOCAL,
        self::STATUS_PENDING_ZOHO_SYNC,
        self::STATUS_SETTLED_PENDING_HANDOVER,
        self::STATUS_SYNCED,
        self::STATUS_SYNC_FAILED,
    ];

    protected $fillable = [
        'uuid',
        'payment_reference',
        'customer_id',
        'collector_id',
        'created_by',
        'confirmed_by',
        'branch_id',
        'assignment_id',
        'visit_id',
        'promise_id',
        'payment_method_id',
        'currency',
        'amount',
        'status',
        'zoho_sync_status',
        'zoho_payment_id',
        'zoho_reference',
        'sync_attempts',
        'last_sync_error',
        'idempotency_key',
        'external_reference',
        'notes',
        'latitude',
        'longitude',
        'gps_accuracy',
        'device_info',
        'ip_address',
        'user_agent',
        'confirmed_at',
        'reversed_at',
        'receipt_id',
        'draft_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'gps_accuracy' => 'decimal:2',
            'sync_attempts' => 'integer',
            'confirmed_at' => 'datetime',
            'reversed_at' => 'datetime',
            'draft_expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, self::CONFIRMED_STATUSES, true);
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(CollectionVisit::class, 'visit_id');
    }

    public function promise(): BelongsTo
    {
        return $this->belongsTo(PromiseToPay::class, 'promise_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    public function syncAttempts(): HasMany
    {
        return $this->hasMany(PaymentSyncAttempt::class);
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(PaymentReversal::class);
    }

    public function latestReversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class)->latestOfMany();
    }
}
