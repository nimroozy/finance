<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'zoho_location_id',
        'customer_id',
        'zoho_invoice_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'currency',
        'total',
        'amount_paid',
        'credits_applied',
        'balance',
        'reporting_tags',
        'zoho_modified_at',
        'last_synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'credits_applied' => 'decimal:4',
            'balance' => 'decimal:4',
            'reporting_tags' => 'array',
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

    public function customFields(): HasMany
    {
        return $this->hasMany(InvoiceCustomField::class);
    }
}
