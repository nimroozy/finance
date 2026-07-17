<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\PurchaseOrder;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class PurchaseOrderPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.purchasing.view') || $this->can($user, 'inventory.purchasing.manage');
    }

    public function view(User $user, PurchaseOrder $model): bool
    {
        return ($this->can($user, 'inventory.purchasing.view') || $this->can($user, 'inventory.purchasing.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.purchasing.manage');
    }

    public function update(User $user, PurchaseOrder $model): bool
    {
        return $this->can($user, 'inventory.purchasing.manage') && $this->sameBranch($user, $model);
    }

    public function approve(User $user, PurchaseOrder $model): bool
    {
        return $this->can($user, 'inventory.purchasing.approve') && $this->sameBranch($user, $model);
    }
}