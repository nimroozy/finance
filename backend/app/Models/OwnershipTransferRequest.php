<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnershipTransferRequest extends Model
{
    protected $fillable = [
        'customer_id', 'branch_id', 'from_collector_id', 'to_collector_id', 'effective_date',
        'reason', 'status', 'requested_by', 'applied_at',
    ];
    protected function casts(): array { return ['effective_date' => 'date', 'applied_at' => 'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function approvals(): HasMany { return $this->hasMany(OwnershipTransferApproval::class, 'transfer_request_id'); }
}
