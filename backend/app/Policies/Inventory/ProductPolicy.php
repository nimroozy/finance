<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class ProductPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.products.view') || $this->can($user, 'inventory.products.manage');
    }

    public function view(User $user, Product $model): bool
    {
        return ($this->can($user, 'inventory.products.view') || $this->can($user, 'inventory.products.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.products.manage');
    }

    public function update(User $user, Product $model): bool
    {
        return $this->can($user, 'inventory.products.manage') && $this->sameBranch($user, $model);
    }
}