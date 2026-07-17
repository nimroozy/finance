<?php

namespace App\Policies\Inventory\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksInventoryAccess
{
    protected function can(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    protected function sameBranch(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }
        if (! isset($model->branch_id)) {
            return true;
        }

        return in_array((int) $model->branch_id, $user->branchIds(), true);
    }
}
