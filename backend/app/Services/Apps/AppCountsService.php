<?php

namespace App\Services\Apps;

use App\Models\Services\Service;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;

class AppCountsService
{
    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, int>
     */
    public function counts(User $actor, ?array $branchIds = null): array
    {
        $branchIds = $branchIds ?? $actor->branchIds();
        if (! $actor->isSuperAdmin() && ! $actor->isCentralFinanceAdmin()) {
            $branchIds = array_values(array_intersect($branchIds, $actor->branchIds()));
        }
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));

        $counts = [
            'tasks' => 0,
            'support' => 0,
            'installations' => 0,
            'services' => 0,
            'noc' => 0,
        ];

        if ($branchIds === []) {
            return $counts;
        }

        if ($actor->can('tasks.view')) {
            $counts['tasks'] = Task::query()
                ->whereIn('branch_id', $branchIds)
                ->where('assignee_id', $actor->id)
                ->whereNotIn('status', [
                    Task::STATUS_COMPLETED,
                    Task::STATUS_APPROVED,
                    Task::STATUS_CANCELLED,
                    Task::STATUS_FAILED,
                ])
                ->count();
        }

        if ($actor->can('tickets.view')) {
            $counts['support'] = Ticket::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', Ticket::WAITING_STATUSES)
                ->count();
        }

        if ($actor->can('installations.view')) {
            $counts['installations'] = Installation::query()
                ->whereIn('branch_id', $branchIds)
                ->whereNotIn('status', [
                    Installation::STATUS_COMPLETED,
                    Installation::STATUS_CANCELLED,
                ])
                ->count();
        }

        if ($actor->can('services.view')) {
            $counts['services'] = Service::query()
                ->whereIn('branch_id', $branchIds)
                ->where('commercial_status', Service::COMMERCIAL_PENDING_ACTIVATION)
                ->count();
        }

        if ($actor->can('services.noc.view') || $actor->can('services.activate')) {
            $counts['noc'] = Service::query()
                ->whereIn('branch_id', $branchIds)
                ->where('operational_status', Service::OPERATIONAL_READY)
                ->where('commercial_status', Service::COMMERCIAL_ACTIVE)
                ->count();
        }

        return $counts;
    }
}
