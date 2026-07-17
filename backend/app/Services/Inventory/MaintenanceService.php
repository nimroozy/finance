<?php

namespace App\Services\Inventory;

use App\Models\Inventory\MaintenancePlan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(
        private TaskService $tasks,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function createPlan(array $data, ?User $actor = null): MaintenancePlan
    {
        return DB::transaction(function () use ($data) {
            $plan = MaintenancePlan::query()->create([
                'branch_id' => $data['branch_id'],
                'name' => $data['name'],
                'equipment_id' => $data['equipment_id'] ?? null,
                'fixed_asset_id' => $data['fixed_asset_id'] ?? null,
                'tower_id' => $data['tower_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'frequency' => $data['frequency'] ?? 'monthly',
                'next_due_date' => $data['next_due_date'] ?? now()->addMonth()->toDateString(),
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->log('inventory.maintenance_plan.created', $plan, null, $plan->toArray(), (int) $plan->branch_id);

            return $plan;
        });
    }

    public function createTaskFromPlan(MaintenancePlan $plan, ?User $actor = null): MaintenancePlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $task = $this->tasks->create([
                'branch_id' => $plan->branch_id,
                'title' => 'Maintenance: '.$plan->name,
                'description' => $plan->notes,
                'type' => 'field',
                'priority' => 'normal',
            ], $actor);

            $plan->last_task_id = $task->id;
            $plan->next_due_date = match ($plan->frequency) {
                'weekly' => now()->addWeek()->toDateString(),
                'quarterly' => now()->addMonths(3)->toDateString(),
                'yearly' => now()->addYear()->toDateString(),
                default => now()->addMonth()->toDateString(),
            };
            $plan->save();

            return $plan->fresh();
        });
    }
}
