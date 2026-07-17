<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\EquipmentSale;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class EquipmentSalePolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.equipment.sales');
    }

    public function view(User $user, EquipmentSale $model): bool
    {
        return ($this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.equipment.sales')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.sales');
    }

    public function update(User $user, EquipmentSale $model): bool
    {
        return $this->can($user, 'inventory.equipment.sales') && $this->sameBranch($user, $model);
    }
}