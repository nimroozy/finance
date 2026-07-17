<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Equipment;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class EquipmentPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.equipment.manage');
    }

    public function view(User $user, Equipment $model): bool
    {
        return ($this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.equipment.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.manage');
    }

    public function update(User $user, Equipment $model): bool
    {
        return $this->can($user, 'inventory.equipment.manage') && $this->sameBranch($user, $model);
    }
}