<?php

namespace App\Policies;

use App\Models\Tickets\Installation;
use App\Models\User;

class InstallationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('installations.view');
    }

    public function view(User $user, Installation $installation): bool
    {
        if (! $user->can('installations.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $installation->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('installations.create');
    }

    public function update(User $user, Installation $installation): bool
    {
        return $user->can('installations.update') && $this->sameBranch($user, $installation);
    }

    public function assign(User $user, Installation $installation): bool
    {
        return $user->can('installations.assign') && $this->sameBranch($user, $installation);
    }

    private function sameBranch(User $user, Installation $installation): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $installation->branch_id, $user->branchIds(), true);
    }
}
