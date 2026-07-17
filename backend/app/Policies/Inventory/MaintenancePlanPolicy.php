<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\MaintenancePlan;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class MaintenancePlanPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.assets.view') || $this->can($user, 'inventory.maintenance.manage');
    }

    public function view(User $user, MaintenancePlan $model): bool
    {
        return ($this->can($user, 'inventory.assets.view') || $this->can($user, 'inventory.maintenance.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.maintenance.manage');
    }

    public function update(User $user, MaintenancePlan $model): bool
    {
        return $this->can($user, 'inventory.maintenance.manage') && $this->sameBranch($user, $model);
    }
}