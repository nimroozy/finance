<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

class ReceiptPolicy
{
    public function view(User $user, Receipt $receipt): bool
    {
        if (! $user->can('receipts.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $receipt->branch_id, $user->branchIds(), true);
    }

    public function print(User $user, Receipt $receipt): bool
    {
        return ($user->can('receipts.print') || $user->can('receipts.manage')) && $this->view($user, $receipt);
    }
}
