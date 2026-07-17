<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\ServiceLocation;
use App\Models\User;
use App\Services\AuditLogger;
use InvalidArgumentException;

class ServiceLocationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ServiceLocation
    {
        if (empty($data['customer_id']) || empty($data['branch_id']) || empty($data['name'])) {
            throw new InvalidArgumentException('Service location requires customer_id, branch_id, and name.');
        }

        $location = ServiceLocation::query()->create([
            'customer_id' => $data['customer_id'],
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'province' => $data['province'] ?? null,
            'district' => $data['district'] ?? null,
            'city' => $data['city'] ?? null,
            'gps_lat' => $data['gps_lat'] ?? null,
            'gps_lng' => $data['gps_lng'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'access_instructions' => $data['access_instructions'] ?? null,
            'rooftop_access' => $data['rooftop_access'] ?? null,
            'power_availability' => $data['power_availability'] ?? null,
            'building_type' => $data['building_type'] ?? null,
            'coverage_notes' => $data['coverage_notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log('service_location.created', $location, null, $location->toArray(), $location->branch_id);

        return $location;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ServiceLocation $location, array $data, ?User $actor = null): ServiceLocation
    {
        $old = $location->toArray();
        $location->fill(collect($data)->only([
            'name', 'address', 'province', 'district', 'city', 'gps_lat', 'gps_lng',
            'contact_name', 'contact_phone', 'whatsapp', 'access_instructions',
            'rooftop_access', 'power_availability', 'building_type', 'coverage_notes', 'is_active',
        ])->all())->save();

        $this->audit->log('service_location.updated', $location, $old, $location->toArray(), $location->branch_id);

        return $location->fresh();
    }
}
