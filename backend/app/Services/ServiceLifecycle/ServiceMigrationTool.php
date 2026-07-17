<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Tickets\Installation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ServiceMigrationTool
{
    public function __construct(private InstallationToServiceConverter $converter) {}

    /**
     * @return array{candidates: list<array<string, mixed>>, count: int}
     */
    public function dryRun(?int $branchId = null): array
    {
        $existingIds = Service::withoutGlobalScopes()->whereNotNull('installation_id')->pluck('installation_id');
        $query = Installation::query()
            ->where('status', Installation::STATUS_COMPLETED)
            ->whereNotNull('customer_id')
            ->whereNotIn('id', $existingIds);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $rows = $query->orderBy('id')->limit(500)->get(['id', 'installation_number', 'branch_id', 'customer_id', 'requested_package', 'status']);

        return [
            'count' => $rows->count(),
            'candidates' => $rows->map(fn (Installation $i) => [
                'installation_id' => $i->id,
                'installation_number' => $i->installation_number,
                'branch_id' => $i->branch_id,
                'customer_id' => $i->customer_id,
                'requested_package' => $i->requested_package,
            ])->all(),
            'mode' => 'dry_run',
        ];
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function apply(?int $branchId = null, ?User $actor = null): array
    {
        $dry = $this->dryRun($branchId);
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($dry['candidates'] as $row) {
            try {
                DB::transaction(function () use ($row, $actor, &$created) {
                    $installation = Installation::query()->findOrFail($row['installation_id']);
                    $this->converter->convert($installation, ['allow_without_package' => true], $actor);
                    $created++;
                });
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = 'installation '.$row['installation_id'].': '.$e->getMessage();
            }
        }

        return compact('created', 'skipped', 'errors');
    }
}
