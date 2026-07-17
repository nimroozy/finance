<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Transfer;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class TransferPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.transfers.view') || $this->can($user, 'inventory.transfers.manage');
    }

    public function view(User $user, Transfer $model): bool
    {
        return ($this->can($user, 'inventory.transfers.view') || $this->can($user, 'inventory.transfers.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.transfers.manage');
    }

    public function update(User $user, Transfer $model): bool
    {
        return $this->can($user, 'inventory.transfers.manage') && $this->sameBranch($user, $model);
    }

    public function approve(User $user, Transfer $model): bool
    {
        return $this->can($user, 'inventory.transfers.approve') && $this->sameBranch($user, $model);
    }
}