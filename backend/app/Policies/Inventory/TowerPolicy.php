<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Tower;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class TowerPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.towers.view') || $this->can($user, 'inventory.towers.manage');
    }

    public function view(User $user, Tower $model): bool
    {
        return ($this->can($user, 'inventory.towers.view') || $this->can($user, 'inventory.towers.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.towers.manage');
    }

    public function update(User $user, Tower $model): bool
    {
        return $this->can($user, 'inventory.towers.manage') && $this->sameBranch($user, $model);
    }
}