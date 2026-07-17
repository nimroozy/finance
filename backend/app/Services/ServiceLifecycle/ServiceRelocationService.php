<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceRelocated;
use App\Models\Services\Service;
use App\Models\Services\ServiceLocation;
use App\Models\Services\ServiceRelocation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceRelocationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(Service $service, array $data, ?User $actor = null): ServiceRelocation
    {
        if (empty($data['new_location_id'])) {
            throw new InvalidArgumentException('Relocation requires new_location_id.');
        }

        $newLocation = ServiceLocation::query()->findOrFail($data['new_location_id']);
        if ((int) $newLocation->customer_id !== (int) $service->customer_id) {
            throw new InvalidArgumentException('New location must belong to the same customer.');
        }

        $relocation = ServiceRelocation::query()->create([
            'service_id' => $service->id,
            'status' => ServiceRelocation::STATUS_REQUESTED,
            'old_location_id' => $service->service_location_id,
            'new_location_id' => $newLocation->id,
            'old_tower_id' => $service->tower_id,
            'new_tower_id' => $data['new_tower_id'] ?? null,
            'coverage_check_id' => $data['coverage_check_id'] ?? null,
            'survey_id' => $data['survey_id'] ?? null,
            'quote_id' => $data['quote_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log('service.relocation_requested', $service, null, $relocation->toArray(), $service->branch_id);

        return $relocation;
    }

    public function complete(ServiceRelocation $relocation, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($relocation, $actor) {
            $service = Service::query()->lockForUpdate()->findOrFail($relocation->service_id);
            $old = ['service_location_id' => $service->service_location_id, 'tower_id' => $service->tower_id];

            $service->service_location_id = $relocation->new_location_id;
            $service->tower_id = $relocation->new_tower_id ?? $service->tower_id;
            $service->updated_by = $actor?->id;
            $service->save();

            $relocation->status = ServiceRelocation::STATUS_COMPLETED;
            $relocation->save();

            $this->audit->log('service.relocated', $service, $old, [
                'service_location_id' => $service->service_location_id,
                'tower_id' => $service->tower_id,
            ], $service->branch_id);

            ServiceRelocated::dispatch($service->id, (int) $service->branch_id);

            return $service->fresh();
        });
    }
}
