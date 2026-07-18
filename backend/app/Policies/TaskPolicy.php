<?php

namespace App\Policies;

use App\Models\Tickets\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ((int) $task->assignee_id === (int) $user->id) {
            return true;
        }

        return in_array((int) $task->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can('tasks.assign') && $this->sameBranch($user, $task);
    }

    public function accept(User $user, Task $task): bool
    {
        return $user->can('tasks.accept') && (
            (int) $task->assignee_id === (int) $user->id
            || $this->sameBranch($user, $task)
        );
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->can('tasks.complete') && (
            (int) $task->assignee_id === (int) $user->id
            || $this->sameBranch($user, $task)
        );
    }

    public function verify(User $user, Task $task): bool
    {
        return $user->can('tasks.verify') && $this->sameBranch($user, $task);
    }

    public function reassign(User $user, Task $task): bool
    {
        return $user->can('tasks.reassign') && $this->sameBranch($user, $task);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $user->can('tasks.cancel') && $this->sameBranch($user, $task);
    }

    private function sameBranch(User $user, Task $task): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $task->branch_id, $user->branchIds(), true);
    }
}
