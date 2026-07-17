<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceCreated;
use App\Models\Customer;
use App\Models\Services\Service;
use App\Models\Services\ServiceLocation;
use App\Models\Services\ServicePackage;
use App\Models\Tickets\Installation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceService
{
    public function __construct(
        private ServiceNumberService $numbers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Service
    {
        if (empty($data['customer_id']) || empty($data['branch_id'])) {
            throw new InvalidArgumentException('Service requires customer_id and branch_id.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $branchId = (int) $data['branch_id'];
            $customer = Customer::withoutGlobalScopes()->findOrFail($data['customer_id']);
            $this->assertSameBranch($branchId, $customer->branch_id !== null ? (int) $customer->branch_id : null, 'Customer');

            $package = null;
            $version = null;
            if (! empty($data['package_id'])) {
                $package = ServicePackage::query()->findOrFail($data['package_id']);
                // Global packages (null branch_id) are allowed; branch packages must match.
                $this->assertSameBranch(
                    $branchId,
                    $package->branch_id !== null ? (int) $package->branch_id : null,
                    'Package',
                    allowNull: true,
                );
                $version = ! empty($data['package_version_id'])
                    ? $package->versions()->whereKey($data['package_version_id'])->firstOrFail()
                    : $package->latestVersion();
            }

            if (! empty($data['service_location_id'])) {
                $location = ServiceLocation::withoutGlobalScopes()->findOrFail($data['service_location_id']);
                $this->assertSameBranch($branchId, (int) $location->branch_id, 'Service location');
            }

            if (! empty($data['installation_id'])) {
                $installation = Installation::withoutGlobalScopes()->findOrFail($data['installation_id']);
                $this->assertSameBranch($branchId, (int) $installation->branch_id, 'Installation');
            }

            $service = Service::query()->create([
                'service_number' => $this->numbers->nextNumber($branchId),
                'customer_id' => $customer->id,
                'zoho_customer_id' => $data['zoho_customer_id'] ?? $customer->zoho_contact_id,
                'branch_id' => $branchId,
                'service_location_id' => $data['service_location_id'] ?? null,
                'installation_id' => $data['installation_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'package_id' => $package?->id,
                'package_version_id' => $version?->id,
                'service_type_code' => $data['service_type_code'] ?? $package?->service_type_code,
                'access_technology_code' => $data['access_technology_code'] ?? $package?->access_technology_code,
                'billing_frequency' => $data['billing_frequency'] ?? $package?->billing_frequency ?? 'monthly',
                'mrr' => $data['mrr'] ?? $version?->monthly_price ?? $package?->monthly_price ?? 0,
                'installation_fee' => $data['installation_fee'] ?? $version?->installation_fee ?? $package?->installation_fee ?? 0,
                'currency' => $data['currency'] ?? 'AFN',
                'contract_id' => $data['contract_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'commercial_status' => $data['commercial_status'] ?? Service::COMMERCIAL_DRAFT,
                'operational_status' => $data['operational_status'] ?? Service::OPERATIONAL_NOT_PROVISIONED,
                'billing_status' => $data['billing_status'] ?? Service::BILLING_NOT_BILLED,
                'ownership_status' => $data['ownership_status'] ?? 'company',
                'technical_owner_id' => $data['technical_owner_id'] ?? null,
                'sales_owner_id' => $data['sales_owner_id'] ?? null,
                'support_owner_id' => $data['support_owner_id'] ?? null,
                'tower_id' => $data['tower_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'network_node' => $data['network_node'] ?? null,
                'vlan' => $data['vlan'] ?? null,
                'static_ip' => $data['static_ip'] ?? null,
                'public_ip' => $data['public_ip'] ?? null,
                'private_ip' => $data['private_ip'] ?? null,
                'pppoe_username_placeholder' => $data['pppoe_username_placeholder'] ?? null,
                'circuit_id' => $data['circuit_id'] ?? null,
                'customer_reference' => $data['customer_reference'] ?? null,
                'sla_template_id' => $data['sla_template_id'] ?? $package?->sla_template_id,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->audit->log('service.created', $service, null, $service->toArray(), $branchId);
            ServiceCreated::dispatch($service->id, $branchId);

            return $service->fresh(['package', 'packageVersion', 'location', 'customer', 'slaTemplate', 'installation']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data, ?User $actor = null): Service
    {
        if (in_array($service->commercial_status, [Service::COMMERCIAL_CANCELLED], true)) {
            throw new InvalidArgumentException('Cancelled services cannot be updated.');
        }

        $allowed = [
            'service_location_id', 'notes', 'metadata', 'technical_owner_id', 'sales_owner_id',
            'support_owner_id', 'tower_id', 'site_id', 'network_node', 'vlan', 'static_ip',
            'public_ip', 'private_ip', 'pppoe_username_placeholder', 'circuit_id',
            'customer_reference', 'sla_template_id', 'expiration_date', 'start_date',
            'installation_id',
        ];

        $branchId = (int) $service->branch_id;

        if (array_key_exists('service_location_id', $data) && $data['service_location_id']) {
            $location = ServiceLocation::withoutGlobalScopes()->findOrFail($data['service_location_id']);
            $this->assertSameBranch($branchId, (int) $location->branch_id, 'Service location');
        }

        if (array_key_exists('installation_id', $data) && $data['installation_id']) {
            $installation = Installation::withoutGlobalScopes()->findOrFail($data['installation_id']);
            $this->assertSameBranch($branchId, (int) $installation->branch_id, 'Installation');
        }

        $old = $service->only($allowed);
        $service->fill(collect($data)->only($allowed)->all());
        $service->updated_by = $actor?->id;
        $service->save();

        $this->audit->log('service.updated', $service, $old, $service->only($allowed), $service->branch_id);

        return $service->fresh([
            'package', 'packageVersion', 'location', 'customer', 'slaTemplate', 'installation',
        ]);
    }

    private function assertSameBranch(int $serviceBranchId, ?int $entityBranchId, string $label, bool $allowNull = false): void
    {
        if ($entityBranchId === null) {
            if ($allowNull) {
                return;
            }

            throw new InvalidArgumentException("{$label} must belong to the same branch as the service.");
        }

        if ($entityBranchId !== $serviceBranchId) {
            throw new InvalidArgumentException("{$label} must belong to the same branch as the service.");
        }
    }
}
