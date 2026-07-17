<?php

namespace App\Services\Tasks;

use App\Events\TaskCreated;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            }

            $task = Task::query()->create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'number' => $this->numbers->nextNumber($branchId),
                'ticket_id' => $data['ticket_id'] ?? null,
                'installation_id' => $data['installation_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => $actor?->id ?? ($data['created_by'] ?? null),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => Task::STATUS_OPEN,
                'priority' => $data['priority'] ?? 'normal',
                'work_type' => $data['work_type'] ?? Task::WORK_OFFICE,
                'checklist' => $data['checklist'] ?? [],
                'due_at' => $data['due_at'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            if ($dependsOnTaskIds !== []) {
                $this->dependencies->sync($task, $dependsOnTaskIds);
            }

            $this->audit->log('task.created', $task, null, $task->toArray(), $branchId);
            TaskCreated::dispatch($task->id, $branchId);

            return $task->fresh(['dependencies']);
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

            $old = $task->only(['title', 'description', 'priority', 'due_at', 'department_id', 'team_id', 'checklist', 'meta']);
            $task->fill(collect($data)->only([
                'title', 'description', 'priority', 'due_at', 'department_id', 'team_id', 'work_type', 'meta',
            ])->all());

            if (array_key_exists('checklist', $data)) {
                $task->checklist = $this->normalizeChecklist($data['checklist']);
            }

            $task->save();

            $this->audit->log('task.updated', $task, $old, $task->only(array_keys($old)), $task->branch_id);

            return $task->fresh();
        });
    }

    /**
     * @param  list<array{key?: string, label: string, done?: bool}>|list<string>  $checklist
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function updateChecklist(Task $task, array $checklist, ?User $actor = null): Task
    {
        $task->checklist = $this->normalizeChecklist($checklist);
        $task->save();

        $this->audit->log('task.checklist_updated', $task, null, [
            'checklist' => $task->checklist,
            'actor_id' => $actor?->id,
        ], $task->branch_id);

        return $task->fresh();
    }

    /**
     * @param  mixed  $checklist
     * @return list<array{key: string, label: string, done: bool}>
     */
    private function normalizeChecklist(mixed $checklist): array
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
