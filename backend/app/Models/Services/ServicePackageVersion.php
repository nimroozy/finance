<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ServicePackageVersion extends Model
{
    protected $fillable = [
        'package_id', 'version', 'effective_date', 'monthly_price', 'installation_fee',
        'download_mbps', 'upload_mbps', 'sla_json', 'terms', 'equipment_requirements',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'monthly_price' => 'decimal:2',
            'installation_fee' => 'decimal:2',
            'sla_json' => 'array',
            'equipment_requirements' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->isDirty(['monthly_price', 'installation_fee', 'download_mbps', 'upload_mbps'])) {
                throw new LogicException('Service package versions are immutable for pricing and speeds. Create a new version.');
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }
}
