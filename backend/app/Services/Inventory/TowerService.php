<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Site;
use App\Models\Inventory\Tower;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TowerService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Tower
    {
        $site = Site::query()->findOrFail($data['site_id'] ?? 0);

        return DB::transaction(function () use ($data, $site) {
            $tower = Tower::query()->create([
                'code' => $data['code'],
                'site_id' => $site->id,
                'branch_id' => $data['branch_id'] ?? $site->branch_id,
                'height_m' => $data['height_m'] ?? null,
                'structure_type' => $data['structure_type'] ?? null,
                'owner' => $data['owner'] ?? null,
                'operational_status' => $data['operational_status'] ?? 'active',
                'installation_date' => $data['installation_date'] ?? null,
                'last_inspection_date' => $data['last_inspection_date'] ?? null,
                'next_inspection_date' => $data['next_inspection_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->log('inventory.tower.created', $tower, null, $tower->toArray(), (int) $tower->branch_id);

            return $tower;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Tower $tower, array $data, ?User $actor = null): Tower
    {
        return DB::transaction(function () use ($tower, $data) {
            $fields = [
                'height_m', 'structure_type', 'owner', 'operational_status',
                'installation_date', 'last_inspection_date', 'next_inspection_date', 'notes',
            ];
            $old = $tower->only($fields);
            $tower->fill(collect($data)->only($fields)->all());
            $tower->save();
            $this->audit->log('inventory.tower.updated', $tower, $old, $tower->only($fields), (int) $tower->branch_id);

            return $tower->fresh();
        });
    }
}
