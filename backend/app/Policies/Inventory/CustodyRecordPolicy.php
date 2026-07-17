<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\CustodyRecord;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class CustodyRecordPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.custody.manage');
    }

    public function view(User $user, CustodyRecord $model): bool
    {
        return ($this->can($user, 'inventory.equipment.view') || $this->can($user, 'inventory.custody.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.custody.manage');
    }

    public function update(User $user, CustodyRecord $model): bool
    {
        return $this->can($user, 'inventory.custody.manage') && $this->sameBranch($user, $model);
    }
}