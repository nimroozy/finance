<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\Installation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_equipment';

    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_CUSTODY = 'custody';
    public const STATUS_REPAIR = 'repair';
    public const STATUS_SOLD = 'sold';
    public const STATUS_SCRAPPED = 'scrapped';
    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'equipment_number', 'product_id', 'serial_number', 'mac_address', 'barcode',
        'branch_id', 'location_id', 'custodian_user_id', 'ownership', 'condition', 'status',
        'supplier_id', 'purchase_cost', 'purchase_date', 'warranty_start', 'warranty_end',
        'customer_id', 'service_ref', 'installation_id', 'site_id', 'tower_id', 'task_id',
        'ticket_id', 'mounting', 'azimuth', 'sector', 'frequency', 'ip_address', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'purchase_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'azimuth' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class, 'tower_id');
    }
}
