<?php

namespace App\Policies;

use App\Models\PaymentReversal;
use App\Models\User;

class PaymentReversalPolicy
{
    public function approve(User $user, PaymentReversal $reversal): bool
    {
        if (! $user->can('reversals.approve')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $reversal->branch_id, $user->branchIds(), true);
    }

    public function reject(User $user, PaymentReversal $reversal): bool
    {
        return $this->approve($user, $reversal);
    }
}
