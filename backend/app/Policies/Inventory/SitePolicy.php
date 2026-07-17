<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Site;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class SitePolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.sites.view') || $this->can($user, 'inventory.sites.manage');
    }

    public function view(User $user, Site $model): bool
    {
        return ($this->can($user, 'inventory.sites.view') || $this->can($user, 'inventory.sites.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.sites.manage');
    }

    public function update(User $user, Site $model): bool
    {
        return $this->can($user, 'inventory.sites.manage') && $this->sameBranch($user, $model);
    }
}