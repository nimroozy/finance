<?php

namespace App\Policies;

use App\Models\CollectionVisit;
use App\Models\User;

class CollectionVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('visits.view');
    }

    public function view(User $user, CollectionVisit $visit): bool
    {
        if (! $user->can('visits.view')) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ($user->isCollectorOnly()) {
            return $user->collector && (int) $user->collector->id === (int) $visit->collector_id;
        }

        return in_array((int) $visit->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('visits.create') || $user->can('visits.manage');
    }

    public function correct(User $user, CollectionVisit $visit): bool
    {
        return $user->can('visits.manage')
            && ($user->isSuperAdmin() || $user->isCentralFinanceAdmin() || in_array((int) $visit->branch_id, $user->branchIds(), true));
    }
}
