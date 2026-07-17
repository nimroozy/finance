<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StockCount;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class StockCountPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.count');
    }

    public function view(User $user, StockCount $model): bool
    {
        return ($this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.count')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.stock.count');
    }

    public function update(User $user, StockCount $model): bool
    {
        return $this->can($user, 'inventory.stock.count') && $this->sameBranch($user, $model);
    }
}