<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceLocation;
use App\Models\Services\ServicePackage;
use App\Models\Tickets\Installation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InstallationToServiceConverter
{
    public function __construct(
        private ServiceService $services,
        private ServiceLocationService $locations,
        private AuditLogger $audit,
    ) {}

    /**
     * Create a draft service from a completed (or ready) installation.
     *
     * @param  array<string, mixed>  $options
     */
    public function convert(Installation $installation, array $options = [], ?User $actor = null): Service
    {
        if (! $installation->customer_id) {
            throw new InvalidArgumentException('Installation must have a customer_id to convert.');
        }

        $existing = Service::withoutGlobalScopes()
            ->where('installation_id', $installation->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($installation, $options, $actor) {
            $locationId = $options['service_location_id'] ?? null;
            if (! $locationId) {
                $location = $this->locations->create([
                    'customer_id' => $installation->customer_id,
                    'branch_id' => $installation->branch_id,
                    'name' => $options['location_name'] ?? ('Install '.$installation->installation_number),
                    'address' => $installation->address,
                    'gps_lat' => $installation->gps_lat,
                    'gps_lng' => $installation->gps_lng,
                    'coverage_notes' => $installation->coverage_notes,
                    'contact_phone' => $installation->phone,
                ], $actor);
                $locationId = $location->id;
            } else {
                ServiceLocation::query()->findOrFail($locationId);
            }

            $packageId = $options['package_id'] ?? null;
            if (! $packageId && ! empty($installation->requested_package)) {
                $packageId = ServicePackage::query()
                    ->where(function ($q) use ($installation) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $installation->branch_id);
                    })
                    ->where(function ($q) use ($installation) {
                        $q->where('code', $installation->requested_package)
                            ->orWhere('name', $installation->requested_package);
                    })
                    ->value('id');
            }

            if (! $packageId && empty($options['allow_without_package'])) {
                throw new InvalidArgumentException('Package is required for conversion (set package_id or allow_without_package).');
            }

            $service = $this->services->create([
                'customer_id' => $installation->customer_id,
                'branch_id' => $installation->branch_id,
                'service_location_id' => $locationId,
                'installation_id' => $installation->id,
                'package_id' => $packageId,
                'installation_fee' => $options['installation_fee'] ?? $installation->installation_fee,
                'mrr' => $options['mrr'] ?? $installation->monthly_fee_estimate,
                'sales_owner_id' => $installation->salesperson_id,
                'technical_owner_id' => $installation->technician_id,
                'notes' => $options['notes'] ?? null,
                'commercial_status' => Service::COMMERCIAL_DRAFT,
                'operational_status' => $installation->status === Installation::STATUS_COMPLETED
                    ? Service::OPERATIONAL_READY
                    : Service::OPERATIONAL_PENDING_INSTALL,
            ], $actor);

            $this->audit->log('service.converted_from_installation', $service, null, [
                'installation_id' => $installation->id,
            ], $installation->branch_id);

            return $service;
        });
    }

    /**
     * Auto-draft after installation completion when package/location can be resolved.
     */
    public function maybeAutoDraft(Installation $installation, ?User $actor = null): ?Service
    {
        if (! config('service_lifecycle.auto_draft_on_installation_complete', true)) {
            return null;
        }
        if ($installation->status !== Installation::STATUS_COMPLETED) {
            return null;
        }
        if (! $installation->customer_id) {
            return null;
        }

        try {
            return $this->convert($installation, [
                'allow_without_package' => true,
            ], $actor);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
