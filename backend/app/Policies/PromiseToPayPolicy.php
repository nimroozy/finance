<?php

namespace App\Policies;

use App\Models\PromiseToPay;
use App\Models\User;

class PromiseToPayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promises.view');
    }

    public function view(User $user, PromiseToPay $promise): bool
    {
        if (! $user->can('promises.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ($user->isCollectorOnly()) {
            return $user->collector && (int) $user->collector->id === (int) $promise->collector_id;
        }

        return in_array((int) $promise->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('promises.create') || $user->can('promises.manage');
    }

    public function manage(User $user, PromiseToPay $promise): bool
    {
        return $user->can('promises.manage')
            && ($user->isSuperAdmin() || $user->isCentralFinanceAdmin() || in_array((int) $promise->branch_id, $user->branchIds(), true));
    }

    public function cancel(User $user, PromiseToPay $promise): bool
    {
        if ($user->can('promises.manage')) {
            return $this->manage($user, $promise);
        }

        return $user->can('promises.create')
            && $user->collector
            && (int) $user->collector->id === (int) $promise->collector_id
            && $promise->isOpen();
    }
}
