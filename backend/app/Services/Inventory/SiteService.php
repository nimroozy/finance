<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SiteService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Site
    {
        if (empty($data['branch_id']) || empty($data['code']) || empty($data['name'])) {
            throw new InvalidArgumentException('branch_id, code, and name are required.');
        }

        return DB::transaction(function () use ($data) {
            $site = Site::query()->create([
                'code' => $data['code'],
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'site_type' => $data['site_type'] ?? null,
                'landlord' => $data['landlord'] ?? null,
                'lease_start' => $data['lease_start'] ?? null,
                'lease_end' => $data['lease_end'] ?? null,
                'rent' => $data['rent'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'power_source' => $data['power_source'] ?? null,
                'power_capacity' => $data['power_capacity'] ?? null,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->log('inventory.site.created', $site, null, $site->toArray(), (int) $site->branch_id);

            return $site;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Site $site, array $data, ?User $actor = null): Site
    {
        return DB::transaction(function () use ($site, $data) {
            $fields = [
                'name', 'address', 'gps_lat', 'gps_lng', 'site_type', 'landlord', 'lease_start',
                'lease_end', 'rent', 'contact_name', 'contact_phone', 'power_source',
                'power_capacity', 'status', 'notes',
            ];
            $old = $site->only($fields);
            $site->fill(collect($data)->only($fields)->all());
            $site->save();
            $this->audit->log('inventory.site.updated', $site, $old, $site->only($fields), (int) $site->branch_id);

            return $site->fresh();
        });
    }
}
