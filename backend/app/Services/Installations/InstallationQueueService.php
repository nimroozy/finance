<?php

namespace App\Services\Installations;

use App\Events\InstallationStatusChanged;
use App\Models\Org\Department;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\TaskTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\InstallationEquipmentService;
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
        private InstallationEquipmentService $installationEquipment,
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
                'installation_number' => $this->numbers->nextNumber($branchId),
                'branch_id' => $branchId,
                'status' => Installation::STATUS_REQUEST_RECEIVED,
                'customer_id' => $data['customer_id'] ?? null,
                'prospect_name' => $data['prospect_name'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? $data['latitude'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? $data['longitude'] ?? null,
                'requested_package' => $data['requested_package'] ?? null,
                'requested_date' => $data['requested_date'] ?? null,
                'coverage_notes' => $data['coverage_notes'] ?? null,
                'tower_site_candidate' => $data['tower_site_candidate'] ?? null,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'technician_id' => $data['technician_id'] ?? null,
                'finance_reviewer_id' => $data['finance_reviewer_id'] ?? null,
                'noc_reviewer_id' => $data['noc_reviewer_id'] ?? null,
                'equipment_status' => $data['equipment_status'] ?? null,
                'installation_fee' => $data['installation_fee'] ?? null,
                'monthly_fee_estimate' => $data['monthly_fee_estimate'] ?? null,
                'notes' => $data['notes'] ?? null,
                'whatsapp_conversation_id' => $data['whatsapp_conversation_id'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $this->audit->log('installation.created', $installation, null, $installation->toArray(), $branchId);

            if (! empty($data['template_code']) || ! empty($data['expand_template'])) {
                $this->expandTaskTemplate($installation, $actor, $data['template_code'] ?? null);
            }

            return $installation->fresh('tasks');
        });
    }

    public function transitionStatus(Installation $installation, string $to, ?User $actor = null, ?string $notes = null): Installation
    {
        $from = $installation->status;
        if (! $installation->canTransitionTo($to)) {
            throw new InvalidArgumentException("Invalid installation transition [{$from} → {$to}].");
        }

        if ($to === Installation::STATUS_COMPLETED) {
            $this->ensureEquipmentReconciled($installation);
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

            if ($to === Installation::STATUS_COMPLETED) {
                try {
                    app(\App\Services\ServiceLifecycle\InstallationToServiceConverter::class)
                        ->maybeAutoDraft($installation->fresh(), $actor);
                } catch (\Throwable) {
                    // Conversion is best-effort; explicit convert endpoint remains available.
                }
            }

            return $installation->fresh();
        });
    }


    public function ensureEquipmentReconciled(Installation $installation): void
    {
        $this->installationEquipment->ensureEquipmentReconciled($installation);
    }

    /**
     * @return list<Task>
     */
    public function expandTaskTemplate(Installation $installation, ?User $actor = null, ?string $templateCode = null): array
    {
        $template = $templateCode
            ? TaskTemplate::query()->where('code', $templateCode)->where('is_active', true)->first()
            : TaskTemplate::query()
                ->where('workflow_type', TaskTemplate::WORKFLOW_INSTALLATION)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        if (! $template) {
            throw new InvalidArgumentException('No installation task template found.');
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
                    'customer_id' => $installation->customer_id,
                    'department_id' => $departmentId,
                    'title' => $step['title'] ?? $key,
                    'description' => $step['description'] ?? null,
                    'type' => $step['type'] ?? ($step['work_type'] ?? Task::TYPE_FIELD),
                    'priority' => $step['priority'] ?? Task::PRIORITY_NORMAL,
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
                    $byKey[$key]->dependsOnTasks()->sync($depIds);
                }
            }

            $this->audit->log('installation.template_expanded', $installation, null, [
                'task_ids' => collect($created)->pluck('id')->all(),
            ], $installation->branch_id);

            return $created;
        });
    }
}
