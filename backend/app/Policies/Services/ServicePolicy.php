<?php

namespace App\Policies\Services;

use App\Models\Services\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->can('services.view') && $this->sameBranch($user, $service);
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can('services.update') && $this->sameBranch($user, $service);
    }

    public function activate(User $user, Service $service): bool
    {
        return $user->can('services.activate') && $this->sameBranch($user, $service);
    }

    public function suspend(User $user, Service $service): bool
    {
        return $user->can('services.suspend') && $this->sameBranch($user, $service);
    }

    public function cancel(User $user, Service $service): bool
    {
        return $user->can('services.cancel') && $this->sameBranch($user, $service);
    }

    public function change(User $user, Service $service): bool
    {
        return $user->can('services.change') && $this->sameBranch($user, $service);
    }

    public function relocate(User $user, Service $service): bool
    {
        return $user->can('services.relocate') && $this->sameBranch($user, $service);
    }

    public function manageFinanceHold(User $user, Service $service): bool
    {
        return $user->can('services.finance_holds.manage') && $this->sameBranch($user, $service);
    }

    public function renew(User $user, Service $service): bool
    {
        return $user->can('services.renew') && $this->sameBranch($user, $service);
    }

    public function confirmOnline(User $user, Service $service): bool
    {
        return ($user->can('services.noc.view') || $user->can('services.activate')) && $this->sameBranch($user, $service);
    }

    private function sameBranch(User $user, Service $service): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $service->branch_id, $user->branchIds(), true);
    }
}
