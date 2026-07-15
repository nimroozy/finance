<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $user->can('payments.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ($user->isCollectorOnly()) {
            return $user->collector && (int) $user->collector->id === (int) $payment->collector_id;
        }

        return in_array((int) $payment->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create') || $user->can('payments.manage');
    }

    public function confirm(User $user, Payment $payment): bool
    {
        if (! ($user->can('payments.confirm') || $user->can('payments.manage') || $user->can('payments.create'))) {
            return false;
        }

        return $this->view($user, $payment);
    }

    public function retrySync(User $user, Payment $payment): bool
    {
        return $user->can('payments.retry_sync') && $this->view($user, $payment);
    }

    public function requestReversal(User $user, Payment $payment): bool
    {
        return $user->can('reversals.request') && $this->view($user, $payment);
    }

    public function manage(User $user): bool
    {
        return $user->can('payments.manage');
    }
}
