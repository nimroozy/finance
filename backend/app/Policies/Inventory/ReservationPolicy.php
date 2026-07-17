<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Reservation;
use App\Models\User;
use App\Policies\Inventory\Concerns\ChecksInventoryAccess;

class ReservationPolicy
{
    use ChecksInventoryAccess;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'inventory.reservations.view') || $this->can($user, 'inventory.reservations.manage');
    }

    public function view(User $user, Reservation $model): bool
    {
        return ($this->can($user, 'inventory.reservations.view') || $this->can($user, 'inventory.reservations.manage')) && $this->sameBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'inventory.reservations.manage');
    }

    public function update(User $user, Reservation $model): bool
    {
        return $this->can($user, 'inventory.reservations.manage') && $this->sameBranch($user, $model);
    }
}