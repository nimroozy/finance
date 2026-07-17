<?php

namespace App\Policies\Crm;

use App\Models\Crm\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        if (! $user->can('crm.leads.view')) {
            return false;
        }

        return $this->sameBranch($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->can('crm.leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.update') && $this->sameBranch($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.assign') && $this->sameBranch($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->can('crm.leads.convert') && $this->sameBranch($user, $lead);
    }

    private function sameBranch(User $user, Lead $lead): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $lead->branch_id, $user->branchIds(), true);
    }
}
