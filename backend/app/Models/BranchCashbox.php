<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class BranchCashbox extends Model
{
    protected $fillable = ['uuid', 'branch_id', 'currency', 'name', 'balance', 'is_active', 'created_by'];
    protected function casts(): array { return ['balance' => 'decimal:4', 'is_active' => 'boolean']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function transactions(): HasMany { return $this->hasMany(BranchCashboxTransaction::class, 'cashbox_id'); }
}
