<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\Installation;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class CustomerEquipment extends Model
{
    protected $table = 'inventory_customer_equipment';

    protected $fillable = [
        'customer_id', 'branch_id', 'service_ref', 'product_id', 'equipment_id',
        'ownership_type', 'sale_price', 'install_fee', 'installed_at', 'returned_at',
        'installation_id', 'service_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'install_fee' => 'decimal:2',
            'installed_at' => 'date',
            'returned_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
