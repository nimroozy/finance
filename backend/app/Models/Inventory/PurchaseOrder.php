<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class PurchaseOrder extends Model
{
    protected $table = 'inventory_purchase_orders';

    protected $fillable = [
        'po_number', 'branch_id', 'supplier_id', 'purchase_request_id', 'status',
        'expected_date', 'total_amount', 'zoho_bill_id', 'zoho_sync_status',
        'zoho_idempotency_key', 'notes', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
