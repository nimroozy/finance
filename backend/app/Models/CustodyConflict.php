<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([BelongsToBranchScope::class])]
class CustodyConflict extends Model
{
    protected $fillable = ['payment_id', 'handover_id', 'reversal_id', 'branch_id', 'status', 'reason', 'requested_by', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
}
