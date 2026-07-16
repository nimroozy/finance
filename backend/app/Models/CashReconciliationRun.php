<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([BelongsToBranchScope::class])]
class CashReconciliationRun extends Model
{
    protected $fillable = ['uuid', 'branch_id', 'cashbox_id', 'reconciliation_date', 'currency', 'opening_balance', 'credits', 'debits', 'expected_balance', 'counted_balance', 'variance_amount', 'status', 'run_by', 'reviewed_by', 'notes'];
    protected function casts(): array { return ['reconciliation_date' => 'date', 'opening_balance' => 'decimal:4', 'credits' => 'decimal:4', 'debits' => 'decimal:4', 'expected_balance' => 'decimal:4', 'counted_balance' => 'decimal:4', 'variance_amount' => 'decimal:4']; }
}
