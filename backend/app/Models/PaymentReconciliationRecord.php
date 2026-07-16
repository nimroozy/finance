<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationRecord extends Model
{
    protected $fillable = [
        'reconciliation_date',
        'branch_id',
        'status',
        'local_confirmed_count',
        'zoho_synced_count',
        'sync_failed_count',
        'reversed_count',
        'local_confirmed_amount',
        'zoho_synced_amount',
        'variance_amount',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'local_confirmed_amount' => 'decimal:4',
            'zoho_synced_amount' => 'decimal:4',
            'variance_amount' => 'decimal:4',
            'details' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
