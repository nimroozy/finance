<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\ServicePackage;
use App\Models\Services\ServicePackageVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServicePackageService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ServicePackage
    {
        if (empty($data['code']) || empty($data['name']) || empty($data['service_type_code']) || empty($data['access_technology_code'])) {
            throw new InvalidArgumentException('Package requires code, name, service_type_code, and access_technology_code.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $package = ServicePackage::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'branch_id' => $data['branch_id'] ?? null,
                'service_type_code' => $data['service_type_code'],
                'access_technology_code' => $data['access_technology_code'],
                'download_mbps' => $data['download_mbps'] ?? null,
                'upload_mbps' => $data['upload_mbps'] ?? null,
                'monthly_price' => $data['monthly_price'] ?? 0,
                'installation_fee' => $data['installation_fee'] ?? 0,
                'billing_frequency' => $data['billing_frequency'] ?? 'monthly',
                'contract_period_months' => $data['contract_period_months'] ?? null,
                'grace_period_days' => $data['grace_period_days'] ?? 0,
                'equipment_template' => $data['equipment_template'] ?? null,
                'sla_template_id' => $data['sla_template_id'] ?? null,
                'zoho_item_id' => $data['zoho_item_id'] ?? null,
                'zoho_sync_status' => $data['zoho_sync_status'] ?? 'pending',
                'is_active' => $data['is_active'] ?? true,
                'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                'expiration_date' => $data['expiration_date'] ?? null,
            ]);

            $this->createVersion($package, [
                'effective_date' => $package->effective_date?->toDateString() ?? now()->toDateString(),
                'monthly_price' => $package->monthly_price,
                'installation_fee' => $package->installation_fee,
                'download_mbps' => $package->download_mbps,
                'upload_mbps' => $package->upload_mbps,
                'sla_json' => $data['sla_json'] ?? null,
                'terms' => $data['terms'] ?? null,
                'equipment_requirements' => $data['equipment_template'] ?? null,
            ], $actor);

            $this->audit->log('service_package.created', $package, null, $package->toArray(), $package->branch_id);

            return $package->fresh('versions');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(ServicePackage $package, array $data, ?User $actor = null): ServicePackage
    {
        // Never mutate historical version prices here — only package metadata / catalog fields.
        $allowed = [
            'name', 'is_active', 'equipment_template', 'sla_template_id', 'zoho_item_id',
            'zoho_sync_status', 'grace_period_days', 'contract_period_months', 'expiration_date',
            'billing_frequency',
        ];

        $old = $package->only($allowed);
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $package->{$key} = $data[$key];
            }
        }
        $package->save();

        $this->audit->log('service_package.updated', $package, $old, $package->only($allowed), $package->branch_id);

        return $package->fresh();
    }

    /**
     * Create a new immutable version (price/speed changes always go here).
     *
     * @param  array<string, mixed>  $data
     */
    public function createVersion(ServicePackage $package, array $data, ?User $actor = null): ServicePackageVersion
    {
        return DB::transaction(function () use ($package, $data) {
            $next = (int) $package->versions()->max('version') + 1;

            $version = ServicePackageVersion::query()->create([
                'package_id' => $package->id,
                'version' => $next,
                'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                'monthly_price' => $data['monthly_price'] ?? $package->monthly_price,
                'installation_fee' => $data['installation_fee'] ?? $package->installation_fee,
                'download_mbps' => $data['download_mbps'] ?? $package->download_mbps,
                'upload_mbps' => $data['upload_mbps'] ?? $package->upload_mbps,
                'sla_json' => $data['sla_json'] ?? null,
                'terms' => $data['terms'] ?? null,
                'equipment_requirements' => $data['equipment_requirements'] ?? null,
            ]);

            // Mirror latest catalog price onto package row for convenience (versions remain SoT for history).
            $package->fill([
                'monthly_price' => $version->monthly_price,
                'installation_fee' => $version->installation_fee,
                'download_mbps' => $version->download_mbps,
                'upload_mbps' => $version->upload_mbps,
            ])->save();

            $this->audit->log('service_package.version_created', $package, null, $version->toArray(), $package->branch_id);

            return $version;
        });
    }
}
