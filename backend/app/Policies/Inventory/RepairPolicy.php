<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Repair;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class RepairPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.repairs.manage');
    }

    public function view(User $user, Repair $model): bool
    {
        return ($this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.repairs.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.repairs.manage');
    }

    public function update(User $user, Repair $model): bool
    {
        return $this->can($user, 'inventory.repairs.manage') && $this->sameBranch($user, $model);
    }
}