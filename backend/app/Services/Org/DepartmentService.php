<?php

namespace App\Services\Org;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return Collection<int, Department>
     */
    public function seedDefaultsForBranch(Branch $branch): Collection
    {
        $defs = config('ticketing.departments', []);

        return DB::transaction(function () use ($branch, $defs) {
            $created = collect();
            foreach ($defs as $def) {
                $dept = Department::query()->firstOrCreate(
                    ['branch_id' => $branch->id, 'code' => $def['code']],
                    [
                        'name_en' => $def['name_en'],
                        'name_fa' => $def['name_fa'] ?? null,
                        'is_central' => false,
                        'is_active' => true,
                    ],
                );
                $created->push($dept);
            }

            $this->audit->log('department.seeded_defaults', $branch, null, [
                'codes' => $created->pluck('code')->all(),
            ], $branch->id);

            return $created;
        });
    }

    /**
     * @return Collection<int, Department>
     */
    public function list(?int $branchId = null, bool $activeOnly = true): Collection
    {
        $q = Department::query()->with('teams')->orderBy('code');
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        return $q->get();
    }

    public function attachUser(Department $department, User $user, bool $isLead = false): Department
    {
        $department->users()->syncWithoutDetaching([
            $user->id => ['is_lead' => $isLead],
        ]);

        $this->audit->log('department.user_attached', $department, null, [
            'user_id' => $user->id,
            'is_lead' => $isLead,
        ], $department->branch_id);

        return $department->load('users');
    }
}
