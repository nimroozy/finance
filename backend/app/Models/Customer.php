<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Customer extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_UNMAPPED = 'unmapped';

    protected $fillable = [
        'branch_id',
        'zoho_contact_id',
        'customer_number',
        'contact_name',
        'company_name',
        'phone',
        'mobile',
        'whatsapp_number',
        'email',
        'billing_address',
        'shipping_address',
        'currency',
        'outstanding_receivable',
        'payment_terms',
        'status',
        'reporting_tags',
        'zoho_created_at',
        'zoho_modified_at',
        'last_synced_at',
        'sync_status',
        'is_unmapped',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_receivable' => 'decimal:4',
            'reporting_tags' => 'array',
            'zoho_created_at' => 'datetime',
            'zoho_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_unmapped' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomerCustomField::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
