<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class Location extends Model
{
    protected $table = 'inventory_locations';

    public const TYPE_WAREHOUSE = 'warehouse';

    public const TYPE_SALES_STORE = 'sales_store';

    public const TYPE_TECHNICAL_STORE = 'technical_store';

    public const TYPE_REPAIR_STORE = 'repair_store';

    public const TYPE_OFFICE = 'office';

    public const TYPE_EMPLOYEE_CUSTODY = 'employee_custody';

    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_TOWER = 'tower';

    public const TYPE_NETWORK_SITE = 'network_site';

    public const TYPE_CUSTOMER_SITE = 'customer_site';

    public const TYPE_IN_TRANSIT = 'in_transit';

    public const TYPE_DAMAGED = 'damaged';

    public const TYPE_QUARANTINE = 'quarantine';

    public const TYPE_SCRAP = 'scrap';

    public const TYPES = [
        self::TYPE_WAREHOUSE, self::TYPE_SALES_STORE, self::TYPE_TECHNICAL_STORE,
        self::TYPE_REPAIR_STORE, self::TYPE_OFFICE, self::TYPE_EMPLOYEE_CUSTODY,
        self::TYPE_VEHICLE, self::TYPE_TOWER, self::TYPE_NETWORK_SITE,
        self::TYPE_CUSTOMER_SITE, self::TYPE_IN_TRANSIT, self::TYPE_DAMAGED,
        self::TYPE_QUARANTINE, self::TYPE_SCRAP,
    ];

    protected $fillable = [
        'code', 'branch_id', 'parent_id', 'type', 'name', 'address', 'gps_lat', 'gps_lng',
        'custodian_user_id', 'allow_sales', 'allow_receiving', 'allow_dispatch',
        'allow_negative', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_sales' => 'boolean',
            'allow_receiving' => 'boolean',
            'allow_dispatch' => 'boolean',
            'allow_negative' => 'boolean',
            'is_active' => 'boolean',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'location_id');
    }
}
