<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Location;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LocationService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Location
    {
        if (empty($data['branch_id'])) {
            throw new InvalidArgumentException('branch_id required.');
        }
        $type = $data['type'] ?? Location::TYPE_WAREHOUSE;
        if (! in_array($type, Location::TYPES, true)) {
            throw new InvalidArgumentException('Invalid location type.');
        }

        return DB::transaction(function () use ($data, $type) {
            $loc = Location::query()->create([
                'code' => $data['code'],
                'branch_id' => $data['branch_id'],
                'parent_id' => $data['parent_id'] ?? null,
                'type' => $type,
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'custodian_user_id' => $data['custodian_user_id'] ?? null,
                'allow_sales' => $data['allow_sales'] ?? false,
                'allow_receiving' => $data['allow_receiving'] ?? false,
                'allow_dispatch' => $data['allow_dispatch'] ?? false,
                'allow_negative' => $data['allow_negative'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->audit->log('inventory.location.created', $loc, null, $loc->toArray(), (int) $loc->branch_id);

            return $loc;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Location $location, array $data, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($location, $data) {
            $fields = [
                'code', 'parent_id', 'type', 'name', 'address', 'gps_lat', 'gps_lng',
                'custodian_user_id', 'allow_sales', 'allow_receiving', 'allow_dispatch',
                'allow_negative', 'is_active',
            ];
            $old = $location->only($fields);
            $location->fill(collect($data)->only($fields)->all());
            $location->save();
            $this->audit->log('inventory.location.updated', $location, $old, $location->only($fields), (int) $location->branch_id);

            return $location->fresh();
        });
    }
}
