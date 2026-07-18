<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tickets\Installation */
class InstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installation_number' => $this->installation_number,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            'prospect_name' => $this->prospect_name,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'gps_lat' => $this->gps_lat,
            'gps_lng' => $this->gps_lng,
            'requested_package' => $this->requested_package,
            'requested_date' => optional($this->requested_date)?->toDateString(),
            'technician_id' => $this->technician_id,
            'salesperson_id' => $this->salesperson_id,
            'equipment_status' => $this->equipment_status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'tasks' => $this->whenLoaded('tasks'),
        ];
    }
}
