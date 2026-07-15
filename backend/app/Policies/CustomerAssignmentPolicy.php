<?php

namespace App\Policies;

use App\Models\CustomerAssignment;
use App\Models\User;

class CustomerAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assignments.view');
    }

    public function view(User $user, CustomerAssignment $assignment): bool
    {
        if (! $user->can('assignments.view')) {
            return false;
        }

        return $this->branchAllowed($user, $assignment) && $this->collectorAllowed($user, $assignment);
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.manage');
    }

    public function reassign(User $user, CustomerAssignment $assignment): bool
    {
        return $user->can('assignments.reassign') && $this->branchAllowed($user, $assignment);
    }

    public function cancel(User $user, CustomerAssignment $assignment): bool
    {
        return $user->can('assignments.cancel') && $this->branchAllowed($user, $assignment);
    }

    public function accept(User $user, CustomerAssignment $assignment): bool
    {
        return $user->isCollectorOnly()
            && $user->collector
            && (int) $user->collector->id === (int) $assignment->collector_id;
    }

    public function export(User $user): bool
    {
        return $user->can('assignments.export');
    }

    protected function branchAllowed(User $user, CustomerAssignment $assignment): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $assignment->branch_id, $user->branchIds(), true);
    }

    protected function collectorAllowed(User $user, CustomerAssignment $assignment): bool
    {
        if (! $user->isCollectorOnly()) {
            return true;
        }

        return $user->collector && (int) $user->collector->id === (int) $assignment->collector_id;
    }
}
