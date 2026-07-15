<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Database\Factories\CollectorWalletFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class CollectorWallet extends Model
{
    /** @use HasFactory<CollectorWalletFactory> */
    use HasFactory;

    protected $fillable = [
        'collector_id',
        'branch_id',
        'currency',
        'balance',
        'pending_handover_balance',
        'last_transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'pending_handover_balance' => 'decimal:4',
            'last_transaction_at' => 'datetime',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CollectorWalletTransaction::class);
    }
}
