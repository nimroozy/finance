<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view') || $user->can('branches.manage');
    }

    public function view(User $user, Branch $branch): bool
    {
        if (! $user->can('branches.view') && ! $user->can('branches.manage')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $branch->id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        if (! $user->can('branches.manage')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $branch->id, $user->branchIds(), true);
    }
}
