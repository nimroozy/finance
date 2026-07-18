<?php

namespace App\Services\Tasks;

use App\Models\Tickets\Task;
use App\Models\Tickets\TaskDependency;
use InvalidArgumentException;

class TaskDependencyService
{
    public function assertCanStart(Task $task): void
    {
        $blocking = $task->dependsOnTasks()
            ->get()
            ->filter(fn (Task $dep) => ! $dep->isCompleteForDependency());

        if ($blocking->isNotEmpty()) {
            $numbers = $blocking->pluck('task_number')->implode(', ');
            throw new InvalidArgumentException("Cannot start task until dependencies complete: {$numbers}");
        }
    }

    public function attach(Task $task, Task $dependsOn, string $type = TaskDependency::TYPE_BLOCKING): void
    {
        if ($task->id === $dependsOn->id) {
            throw new InvalidArgumentException('A task cannot depend on itself.');
        }

        TaskDependency::query()->firstOrCreate([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
        ], ['type' => $type]);
    }

    /**
     * @param  list<int>  $dependsOnIds
     */
    public function sync(Task $task, array $dependsOnIds, string $type = TaskDependency::TYPE_BLOCKING): void
    {
        $dependsOnIds = array_values(array_unique(array_map('intval', $dependsOnIds)));
        if (in_array((int) $task->id, $dependsOnIds, true)) {
            throw new InvalidArgumentException('A task cannot depend on itself.');
        }

        $task->dependsOnTasks()->sync(
            collect($dependsOnIds)->mapWithKeys(fn (int $id) => [$id => ['type' => $type]])->all()
        );
    }

    public function hasIncompleteBlockers(Task $task): bool
    {
        return $task->dependsOnTasks()
            ->get()
            ->contains(fn (Task $dep) => ! $dep->isCompleteForDependency());
    }
}
