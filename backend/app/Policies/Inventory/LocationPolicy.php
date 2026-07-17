<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Location;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class LocationPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.locations.view') || $this->can($user, 'inventory.locations.manage');
    }

    public function view(User $user, Location $model): bool
    {
        return ($this->can($user, 'inventory.locations.view') || $this->can($user, 'inventory.locations.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.locations.manage');
    }

    public function update(User $user, Location $model): bool
    {
        return $this->can($user, 'inventory.locations.manage') && $this->sameBranch($user, $model);
    }
}