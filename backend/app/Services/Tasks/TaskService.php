<?php

namespace App\Services\Tasks;

use App\Events\TaskCreated;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskService
{
    public function __construct(
        private TaskNumberService $numbers,
        private TaskDependencyService $dependencies,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $dependsOnTaskIds
     */
    public function create(array $data, ?User $actor = null, array $dependsOnTaskIds = []): Task
    {
        if (empty($data['branch_id']) || empty($data['title'])) {
            throw new InvalidArgumentException('Task requires branch_id and title.');
        }

        return DB::transaction(function () use ($data, $actor, $dependsOnTaskIds) {
            $branchId = (int) $data['branch_id'];

            if (! empty($data['ticket_id'])) {
                $ticket = Ticket::query()->findOrFail($data['ticket_id']);
                $branchId = (int) $ticket->branch_id;
                $data['branch_id'] = $branchId;
                $data['customer_id'] ??= $ticket->customer_id;
            }

            $type = $data['type'] ?? Task::TYPE_OFFICE;
            if (! in_array($type, [Task::TYPE_FIELD, Task::TYPE_OFFICE], true)) {
                throw new InvalidArgumentException("Invalid task type [{$type}].");
            }

            $task = Task::query()->create([
                'task_number' => $this->numbers->nextNumber($branchId),
                'branch_id' => $branchId,
                'department_id' => $data['department_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => $actor?->id ?? ($data['created_by'] ?? null),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $type,
                'priority' => $data['priority'] ?? Task::PRIORITY_NORMAL,
                'status' => Task::STATUS_PENDING,
                'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
                'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'location' => $data['location'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'installation_id' => $data['installation_id'] ?? null,
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'checklist' => $this->normalizeChecklist($data['checklist'] ?? []),
                'required_evidence' => $data['required_evidence'] ?? null,
                'requires_approval' => (bool) ($data['requires_approval'] ?? false),
                'approver_id' => $data['approver_id'] ?? null,
            ]);

            if ($dependsOnTaskIds !== []) {
                $this->dependencies->sync($task, $dependsOnTaskIds);
            }

            $this->audit->log('task.created', $task, null, $task->toArray(), $branchId);
            TaskCreated::dispatch($task->id, $branchId);

            return $task->fresh(['dependsOnTasks']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, ?User $actor = null): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            if ($task->isTerminal()) {
                throw new InvalidArgumentException('Terminal tasks cannot be updated.');
            }

            $fields = [
                'title', 'description', 'priority', 'due_at', 'department_id', 'team_id',
                'location', 'gps_lat', 'gps_lng', 'scheduled_start_at', 'scheduled_end_at',
                'requires_approval', 'approver_id', 'required_evidence',
            ];

            $old = $task->only($fields);
            $task->fill(collect($data)->only($fields)->all());

            if (array_key_exists('checklist', $data)) {
                $task->checklist = $this->normalizeChecklist($data['checklist']);
            }
            if (array_key_exists('type', $data) && in_array($data['type'], [Task::TYPE_FIELD, Task::TYPE_OFFICE], true)) {
                $task->type = $data['type'];
            }

            $task->save();

            $this->audit->log('task.updated', $task, $old, $task->only(array_keys($old)), $task->branch_id);

            return $task->fresh();
        });
    }

    /**
     * @param  mixed  $checklist
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function normalizeChecklist(mixed $checklist): array
    {
        if (! is_array($checklist)) {
            return [];
        }

        $out = [];
        foreach ($checklist as $i => $item) {
            if (is_string($item)) {
                $out[] = ['key' => 'item_'.$i, 'label' => $item, 'done' => false];

                continue;
            }
            if (! is_array($item) || empty($item['label'])) {
                continue;
            }
            $out[] = [
                'key' => (string) ($item['key'] ?? 'item_'.$i),
                'label' => (string) $item['label'],
                'done' => (bool) ($item['done'] ?? false),
            ];
        }

        return $out;
    }
}
