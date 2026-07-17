<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\FixedAsset;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class FixedAssetPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.assets.view') || $this->can($user, 'inventory.assets.manage');
    }

    public function view(User $user, FixedAsset $model): bool
    {
        return ($this->can($user, 'inventory.assets.view') || $this->can($user, 'inventory.assets.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.assets.manage');
    }

    public function update(User $user, FixedAsset $model): bool
    {
        return $this->can($user, 'inventory.assets.manage') && $this->sameBranch($user, $model);
    }
}