<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class CashHandoverRequest extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CLARIFICATION = 'clarification';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['uuid', 'handover_number', 'collector_id', 'branch_id', 'currency', 'declared_amount', 'expected_wallet_amount', 'selected_payment_total', 'counted_amount', 'approved_amount', 'variance_amount', 'variance_type', 'status', 'notes', 'review_notes', 'cashbox_id', 'created_by', 'submitted_by', 'reviewed_by', 'approved_by', 'submitted_at', 'reviewed_at', 'approved_at', 'rejected_at'];

    protected function casts(): array
    {
        return array_fill_keys(['declared_amount', 'expected_wallet_amount', 'selected_payment_total', 'counted_amount', 'approved_amount', 'variance_amount'], 'decimal:4')
            + ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function collector(): BelongsTo { return $this->belongsTo(Collector::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function cashbox(): BelongsTo { return $this->belongsTo(BranchCashbox::class); }
    public function items(): HasMany { return $this->hasMany(CashHandoverItem::class, 'handover_id'); }
    public function history(): HasMany { return $this->hasMany(CashHandoverStatusHistory::class, 'handover_id'); }
    public function reviews(): HasMany { return $this->hasMany(CashHandoverReview::class, 'handover_id'); }
}
