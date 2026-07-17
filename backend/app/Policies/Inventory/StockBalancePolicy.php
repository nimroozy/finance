<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\StockBalance;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class StockBalancePolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.adjust');
    }

    public function view(User $user, StockBalance $model): bool
    {
        return ($this->can($user, 'inventory.stock.view') || $this->can($user, 'inventory.stock.adjust')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.stock.adjust');
    }

    public function update(User $user, StockBalance $model): bool
    {
        return $this->can($user, 'inventory.stock.adjust') && $this->sameBranch($user, $model);
    }

    public function adjust(User $user): bool
    {
        return $this->can($user, 'inventory.stock.adjust');
    }
}