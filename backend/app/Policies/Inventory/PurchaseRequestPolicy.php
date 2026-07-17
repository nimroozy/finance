<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\PurchaseRequest;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class PurchaseRequestPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.purchasing.view') || $this->can($user, 'inventory.purchasing.manage');
    }

    public function view(User $user, PurchaseRequest $model): bool
    {
        return ($this->can($user, 'inventory.purchasing.view') || $this->can($user, 'inventory.purchasing.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.purchasing.manage');
    }

    public function update(User $user, PurchaseRequest $model): bool
    {
        return $this->can($user, 'inventory.purchasing.manage') && $this->sameBranch($user, $model);
    }

    public function approve(User $user, PurchaseRequest $model): bool
    {
        return $this->can($user, 'inventory.purchasing.approve') && $this->sameBranch($user, $model);
    }
}