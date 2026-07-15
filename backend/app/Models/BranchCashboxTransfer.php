<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class BranchCashboxTransfer extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REVERSED = 'reversed';
    protected $fillable = ['uuid', 'transfer_number', 'from_cashbox_id', 'to_cashbox_id', 'branch_id', 'type', 'currency', 'amount', 'status', 'bank_name', 'bank_reference', 'deposited_on', 'notes', 'created_by', 'approved_by', 'received_by', 'submitted_at', 'approved_at', 'sent_at', 'received_at', 'reversed_at'];
    protected function casts(): array { return ['amount' => 'decimal:4', 'deposited_on' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'received_at' => 'datetime', 'reversed_at' => 'datetime']; }
    public function fromCashbox(): BelongsTo { return $this->belongsTo(BranchCashbox::class, 'from_cashbox_id'); }
    public function toCashbox(): BelongsTo { return $this->belongsTo(BranchCashbox::class, 'to_cashbox_id'); }
}
