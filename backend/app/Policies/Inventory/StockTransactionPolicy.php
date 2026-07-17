<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StockTransaction;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class StockTransactionPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.view');
    }

    public function view(User $user, StockTransaction $model): bool
    {
        return ($this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.view')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.stock.view');
    }

    public function update(User $user, StockTransaction $model): bool
    {
        return $this->can($user, 'inventory.stock.view') && $this->sameBranch($user, $model);
    }
}