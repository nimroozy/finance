<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Inventory\CustomerEquipment;
use App\Models\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Service $service */
        $service = $this->resource;

        return [
            'id' => $service->id,
            'service_number' => $service->service_number,
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'service_location_id' => $service->service_location_id,
            'installation_id' => $service->installation_id,
            'package_id' => $service->package_id,
            'package_version_id' => $service->package_version_id,
            'service_type_code' => $service->service_type_code,
            'access_technology_code' => $service->access_technology_code,
            'billing_frequency' => $service->billing_frequency,
            'mrr' => $service->mrr,
            'installation_fee' => $service->installation_fee,
            'currency' => $service->currency,
            'commercial_status' => $service->commercial_status,
            'operational_status' => $service->operational_status,
            'billing_status' => $service->billing_status,
            'ownership_status' => $service->ownership_status,
            'technical_owner_id' => $service->technical_owner_id,
            'sales_owner_id' => $service->sales_owner_id,
            'support_owner_id' => $service->support_owner_id,
            'tower_id' => $service->tower_id,
            'site_id' => $service->site_id,
            'network_node' => $service->network_node,
            'vlan' => $service->vlan,
            'static_ip' => $service->static_ip,
            'public_ip' => $service->public_ip,
            'private_ip' => $service->private_ip,
            'pppoe_username_placeholder' => $service->pppoe_username_placeholder,
            'circuit_id' => $service->circuit_id,
            'customer_reference' => $service->customer_reference,
            'zoho_customer_id' => $service->zoho_customer_id,
            'sla_template_id' => $service->sla_template_id,
            'start_date' => optional($service->start_date)?->toDateString(),
            'activation_date' => optional($service->activation_date)?->toIso8601String(),
            'suspension_date' => optional($service->suspension_date)?->toIso8601String(),
            'reactivation_date' => optional($service->reactivation_date)?->toIso8601String(),
            'cancellation_date' => optional($service->cancellation_date)?->toIso8601String(),
            'expiration_date' => optional($service->expiration_date)?->toDateString(),
            'notes' => $service->notes,
            'metadata' => $service->metadata,
            'customer' => $this->whenLoaded('customer'),
            'package' => $this->whenLoaded('package'),
            'package_version' => $this->whenLoaded('packageVersion'),
            'location' => $this->whenLoaded('location'),
            'installation' => $this->whenLoaded('installation'),
            'sla' => $this->whenLoaded('slaTemplate', fn () => $service->slaTemplate),
            'sla_template' => $this->whenLoaded('slaTemplate'),
            'technical_owner' => $this->whenLoaded('technicalOwner'),
            'sales_owner' => $this->whenLoaded('salesOwner'),
            'support_owner' => $this->whenLoaded('supportOwner'),
            'equipment_summary' => $this->equipmentSummary($service),
            'created_at' => optional($service->created_at)?->toIso8601String(),
            'updated_at' => optional($service->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array{total: int, installed: int, returned: int, by_status: array<string, int>}
     */
    private function equipmentSummary(Service $service): array
    {
        $rows = CustomerEquipment::withoutGlobalScopes()
            ->where('service_id', $service->id)
            ->get(['id', 'status']);

        $byStatus = [];
        $installed = 0;
        $returned = 0;
        foreach ($rows as $row) {
            $status = (string) ($row->status ?? 'unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            if (in_array($status, ['installed', 'active'], true)) {
                $installed++;
            }
            if (in_array($status, ['returned', 'recovered'], true)) {
                $returned++;
            }
        }

        return [
            'total' => $rows->count(),
            'installed' => $installed,
            'returned' => $returned,
            'by_status' => $byStatus,
        ];
    }
}
