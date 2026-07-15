<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'amount',
        'currency',
        'status',
        'invoice_balance_before',
        'invoice_balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'invoice_balance_before' => 'decimal:4',
            'invoice_balance_after' => 'decimal:4',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
