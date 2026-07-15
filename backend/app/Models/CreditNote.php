<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'zoho_creditnote_id',
        'number',
        'total',
        'balance',
        'status',
        'creditnote_date',
        'zoho_modified_at',
        'last_synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'balance' => 'decimal:4',
            'creditnote_date' => 'date',
            'zoho_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
