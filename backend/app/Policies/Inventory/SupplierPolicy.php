<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Supplier;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class SupplierPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.suppliers.view') || $this->can($user, 'inventory.suppliers.manage');
    }

    public function view(User $user, Supplier $model): bool
    {
        return ($this->can($user, 'inventory.suppliers.view') || $this->can($user, 'inventory.suppliers.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.suppliers.manage');
    }

    public function update(User $user, Supplier $model): bool
    {
        return $this->can($user, 'inventory.suppliers.manage') && $this->sameBranch($user, $model);
    }
}