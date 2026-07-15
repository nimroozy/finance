<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class BranchCashboxTransaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['cashbox_id', 'branch_id', 'handover_id', 'type', 'amount', 'balance_before', 'balance_after', 'currency', 'reference', 'notes', 'created_by', 'created_at'];
    protected function casts(): array { return ['amount' => 'decimal:4', 'balance_before' => 'decimal:4', 'balance_after' => 'decimal:4', 'created_at' => 'datetime']; }
    public function cashbox(): BelongsTo { return $this->belongsTo(BranchCashbox::class); }
}
