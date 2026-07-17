<?php

namespace App\Models\Services;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Global packages use null branch_id; branch filtering is applied in ServicePackageService. */
class ServicePackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'branch_id', 'service_type_code', 'access_technology_code',
        'download_mbps', 'upload_mbps', 'monthly_price', 'installation_fee',
        'billing_frequency', 'contract_period_months', 'grace_period_days',
        'equipment_template', 'sla_template_id', 'zoho_item_id', 'zoho_sync_status',
        'is_active', 'effective_date', 'expiration_date',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'equipment_template' => 'array',
            'is_active' => 'boolean',
            'effective_date' => 'date',
            'expiration_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServicePackageVersion::class, 'package_id');
    }

    public function slaTemplate(): BelongsTo
    {
        return $this->belongsTo(ServiceSlaTemplate::class, 'sla_template_id');
    }

    public function latestVersion(): ?ServicePackageVersion
    {
        return $this->versions()->orderByDesc('version')->first();
    }
}
