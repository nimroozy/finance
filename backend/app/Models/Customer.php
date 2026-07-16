<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

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
        'area',
        'district',
        'latitude',
        'longitude',
        'currency',
        'outstanding_receivable',
        'last_payment_date',
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
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_payment_date' => 'date',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(CustomerAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(CustomerAssignment::class)->where('is_active', true);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function promises(): HasMany
    {
        return $this->hasMany(PromiseToPay::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CollectionVisit::class);
    }

    public function isAssignable(): bool
    {
        return ! $this->is_unmapped
            && $this->branch_id !== null
            && $this->status === self::STATUS_ACTIVE;
    }
}
