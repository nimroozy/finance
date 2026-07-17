<?php

namespace App\Services\Tasks;

use App\Models\Task;
use InvalidArgumentException;

class TaskDependencyService
{
    public function assertCanStart(Task $task): void
    {
        $blocking = $task->dependencies()
            ->get()
            ->filter(fn (Task $dep) => ! $dep->isCompleteForDependency());

        if ($blocking->isNotEmpty()) {
            $numbers = $blocking->pluck('number')->implode(', ');
            throw new InvalidArgumentException("Cannot start task until dependencies complete: {$numbers}");
        }
    }

    public function attach(Task $task, Task $dependsOn): void
    {
        if ($task->id === $dependsOn->id) {
            throw new InvalidArgumentException('A task cannot depend on itself.');
        }

        $task->dependencies()->syncWithoutDetaching([$dependsOn->id]);
    }

    /**
     * @param  list<int>  $dependsOnIds
     */
    public function sync(Task $task, array $dependsOnIds): void
    {
        if (in_array($task->id, $dependsOnIds, true)) {
            throw new InvalidArgumentException('A task cannot depend on itself.');
        }

        $task->dependencies()->sync($dependsOnIds);
    }

    public function hasIncompleteBlockers(Task $task): bool
    {
        return $task->dependencies()
            ->get()
            ->contains(fn (Task $dep) => ! $dep->isCompleteForDependency());
    }
}
