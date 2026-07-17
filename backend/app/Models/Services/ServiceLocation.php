<?php

namespace App\Models\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class ServiceLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'branch_id', 'name', 'address', 'province', 'district', 'city',
        'gps_lat', 'gps_lng', 'contact_name', 'contact_phone', 'whatsapp',
        'access_instructions', 'rooftop_access', 'power_availability', 'building_type',
        'coverage_notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'rooftop_access' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'service_location_id');
    }
}
