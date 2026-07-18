<?php

namespace App\Policies;

use App\Models\Tickets\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view') || $user->can('tickets.view_all_branch') || $user->can('tickets.view_all');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->can('tickets.view_all') || $user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $ticket->branch_id, $user->branchIds(), true);
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.update') && $this->sameBranch($user, $ticket);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.assign') && $this->sameBranch($user, $ticket);
    }

    public function resolve(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.resolve') && $this->sameBranch($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.close') && $this->sameBranch($user, $ticket);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.reopen') && $this->sameBranch($user, $ticket);
    }

    private function sameBranch(User $user, Ticket $ticket): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin() || $user->can('tickets.view_all')) {
            return true;
        }

        return in_array((int) $ticket->branch_id, $user->branchIds(), true);
    }
}
