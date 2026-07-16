<?php

namespace App\Policies;

use App\Models\CollectionRoute;
use App\Models\User;

class CollectionRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('routes.view');
    }

    public function view(User $user, CollectionRoute $route): bool
    {
        if (! $user->can('routes.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ($user->isCollectorOnly()) {
            return $user->collector && (int) $user->collector->id === (int) $route->collector_id;
        }

        return in_array((int) $route->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('routes.manage');
    }

    public function update(User $user, CollectionRoute $route): bool
    {
        return $user->can('routes.manage') && $this->branchOk($user, $route);
    }

    public function manage(User $user, CollectionRoute $route): bool
    {
        return $this->update($user, $route);
    }

    protected function branchOk(User $user, CollectionRoute $route): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $route->branch_id, $user->branchIds(), true);
    }
}
