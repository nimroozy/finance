<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable collector wallet ledger row — never update/delete after insert.
 */
class CollectorWalletTransaction extends Model
{
    public $timestamps = false;

    public const TYPE_PAYMENT_CREDIT = 'payment_credit';

    public const TYPE_REVERSAL_DEBIT = 'reversal_debit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'collector_wallet_id',
        'collector_id',
        'branch_id',
        'payment_id',
        'reversal_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'reference',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CollectorWallet::class, 'collector_wallet_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }
}
