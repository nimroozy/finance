<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ProductCategory;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class ProductCategoryPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.products.view') || $this->can($user, 'inventory.categories.manage');
    }

    public function view(User $user, ProductCategory $model): bool
    {
        return ($this->can($user, 'inventory.products.view') || $this->can($user, 'inventory.categories.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.categories.manage');
    }

    public function update(User $user, ProductCategory $model): bool
    {
        return $this->can($user, 'inventory.categories.manage') && $this->sameBranch($user, $model);
    }
}