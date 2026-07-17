<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class EquipmentSale extends Model
{
    protected $table = 'inventory_equipment_sales';

    protected $fillable = [
        'sale_number', 'branch_id', 'customer_id', 'location_id', 'status', 'total_amount',
        'zoho_invoice_id', 'zoho_sync_status', 'zoho_idempotency_key', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentSaleItem::class, 'sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
