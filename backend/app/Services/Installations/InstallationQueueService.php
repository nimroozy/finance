<?php

namespace App\Services\Installations;

use App\Events\InstallationStatusChanged;
use App\Models\Department;
use App\Models\Installation;
use App\Models\InstallationTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InstallationQueueService
{
    public function __construct(
        private InstallationNumberService $numbers,
        private TaskService $tasks,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Installation
    {
        if (empty($data['branch_id'])) {
            throw new InvalidArgumentException('Installation requires branch_id.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $branchId = (int) $data['branch_id'];
            $installation = Installation::query()->create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'number' => $this->numbers->nextNumber($branchId),
                'customer_id' => $data['customer_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'created_by' => $actor?->id,
                'status' => Installation::STATUS_DRAFT,
                'address' => $data['address'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'notes' => $data['notes'] ?? null,
                'meta' => array_merge([
                    'inventory_reservation' => 'pending_stage9',
                    'radius_activation' => 'pending_stage10',
                ], $data['meta'] ?? []),
            ]);

            $this->audit->log('installation.created', $installation, null, $installation->toArray(), $branchId);

            if (! empty($data['expand_template']) && $installation->template_id) {
                $this->expandTaskTemplate($installation, $actor);
            }

            return $installation->fresh('tasks');
        });
    }

    public function transitionStatus(Installation $installation, string $to, ?User $actor = null, ?string $notes = null): Installation
    {
        $from = $installation->status;
        $allowed = Installation::ALLOWED_TRANSITIONS[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid installation transition [{$from} → {$to}].");
        }

        return DB::transaction(function () use ($installation, $from, $to, $actor, $notes) {
            $installation->status = $to;
            if ($notes) {
                $installation->notes = trim(($installation->notes ? $installation->notes."\n" : '').$notes);
            }
            $installation->save();

            $this->audit->log('installation.status_changed', $installation, ['status' => $from], [
                'status' => $to,
                'actor_id' => $actor?->id,
            ], $installation->branch_id, $notes);

            InstallationStatusChanged::dispatch($installation->id, (int) $installation->branch_id, $from, $to);

            return $installation->fresh();
        });
    }

    /**
     * Expand template steps into tasks with dependencies.
     * Inventory reservation and Radius activation remain placeholders until Stages 9/10.
     *
     * @return list<Task>
     */
    public function expandTaskTemplate(Installation $installation, ?User $actor = null): array
    {
        $template = $installation->template_id
            ? InstallationTaskTemplate::query()->find($installation->template_id)
            : null;

        if (! $template) {
            throw new InvalidArgumentException('Installation has no task template.');
        }

        $steps = $template->steps ?? [];
        if ($steps === []) {
            return [];
        }

        return DB::transaction(function () use ($installation, $steps, $actor) {
            /** @var array<string, Task> $byKey */
            $byKey = [];
            $created = [];

            foreach ($steps as $step) {
                $key = (string) ($step['key'] ?? Str::slug($step['title'] ?? 'step'));
                $deptCode = $step['department_code'] ?? null;
                $departmentId = null;
                if ($deptCode) {
                    $departmentId = Department::query()
                        ->where('code', $deptCode)
                        ->where(fn ($q) => $q->where('branch_id', $installation->branch_id)->orWhereNull('branch_id'))
                        ->value('id');
                }

                $task = $this->tasks->create([
                    'branch_id' => $installation->branch_id,
                    'installation_id' => $installation->id,
                    'ticket_id' => $installation->ticket_id,
                    'department_id' => $departmentId,
                    'title' => $step['title'] ?? $key,
                    'description' => $step['description'] ?? null,
                    'work_type' => $step['work_type'] ?? Task::WORK_FIELD,
                    'priority' => $step['priority'] ?? 'normal',
                    'meta' => [
                        'template_step_key' => $key,
                        'inventory_placeholder' => $step['inventory'] ?? null,
                        'radius_placeholder' => $step['radius'] ?? null,
                    ],
                ], $actor);

                $byKey[$key] = $task;
                $created[] = $task;
            }

            foreach ($steps as $step) {
                $key = (string) ($step['key'] ?? Str::slug($step['title'] ?? 'step'));
                $deps = $step['depends_on'] ?? [];
                if (! is_array($deps) || $deps === [] || ! isset($byKey[$key])) {
                    continue;
                }
                $depIds = [];
                foreach ($deps as $depKey) {
                    if (isset($byKey[$depKey])) {
                        $depIds[] = $byKey[$depKey]->id;
                    }
                }
                if ($depIds !== []) {
                    $byKey[$key]->dependencies()->sync($depIds);
                }
            }

            $this->audit->log('installation.template_expanded', $installation, null, [
                'task_ids' => collect($created)->pluck('id')->all(),
            ], $installation->branch_id);

            return $created;
        });
    }
}
