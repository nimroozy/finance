<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class CustodyConflict extends Model
{
    protected $fillable = [
        'payment_id',
        'handover_id',
        'reversal_id',
        'branch_id',
        'status',
        'reason',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'zoho_void_status',
        'zoho_void_error',
        'cashbox_debit_transaction_id',
        'cashbox_insufficient',
        'manual_review_reason',
        'critical_alert',
        'critical_alert_message',
        'local_completed_at',
        'zoho_completed_at',
        'threshold_tier',
        'approved_amount',
        'details',
        'payment_reconciliation_record_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'local_completed_at' => 'datetime',
            'zoho_completed_at' => 'datetime',
            'cashbox_insufficient' => 'boolean',
            'critical_alert' => 'boolean',
            'approved_amount' => 'decimal:4',
            'details' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(CashHandoverRequest::class, 'handover_id');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(PaymentReversal::class, 'reversal_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
