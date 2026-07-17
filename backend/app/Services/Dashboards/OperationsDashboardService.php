<?php

namespace App\Services\Dashboards;

use App\Models\Escalation;
use App\Models\Installation;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class OperationsDashboardService
{
    /**
     * @param  list<int>|null  $branchIds  null = all branches
     * @return array<string, mixed>
     */
    public function supportSummary(?array $branchIds = null): array
    {
        $tickets = $this->ticketQuery($branchIds);

        return [
            'open' => (clone $tickets)->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED, Ticket::STATUS_RESOLVED])->count(),
            'waiting_customer' => (clone $tickets)->where('status', Ticket::STATUS_WAITING_CUSTOMER)->count(),
            'breached' => (clone $tickets)->whereNotNull('breached_at')->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])->count(),
            'resolved_today' => (clone $tickets)->whereDate('resolved_at', today())->count(),
            'by_priority' => (clone $tickets)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->select('priority', DB::raw('count(*) as total'))
                ->groupBy('priority')
                ->pluck('total', 'priority')
                ->all(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function technicalSummary(?array $branchIds = null): array
    {
        $tasks = $this->taskQuery($branchIds)->where('work_type', Task::WORK_FIELD);

        return [
            'open_field' => (clone $tasks)->whereNotIn('status', [Task::STATUS_VERIFIED, Task::STATUS_CANCELLED, Task::STATUS_COMPLETED])->count(),
            'traveling' => (clone $tasks)->where('status', Task::STATUS_TRAVELING)->count(),
            'in_progress' => (clone $tasks)->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'blocked' => (clone $tasks)->where('status', Task::STATUS_BLOCKED)->count(),
            'completed_today' => (clone $tasks)->whereDate('completed_at', today())->count(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function nocSummary(?array $branchIds = null): array
    {
        $escalations = Escalation::query()->where('status', 'open');
        $tickets = $this->ticketQuery($branchIds);
        if ($branchIds !== null) {
            $escalations->whereIn('branch_id', $branchIds);
        }

        return [
            'open_escalations' => $escalations->count(),
            'escalated_tickets' => (clone $tickets)->where('status', Ticket::STATUS_ESCALATED)->count(),
            'sla_breached' => (clone $tickets)->whereNotNull('breached_at')->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])->count(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function financeSummary(?array $branchIds = null): array
    {
        $installs = Installation::query();
        if ($branchIds !== null) {
            $installs->whereIn('branch_id', $branchIds);
        }

        return [
            'awaiting_finance' => (clone $installs)->where('status', Installation::STATUS_AWAITING_FINANCE)->count(),
            'approved' => (clone $installs)->where('status', Installation::STATUS_APPROVED)->count(),
            'finance_related_tickets' => $this->ticketQuery($branchIds)
                ->where(function ($q) {
                    $q->where('category', 'finance')->orWhere('category', 'billing');
                })
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->count(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    public function managerSummary(?array $branchIds = null): array
    {
        return [
            'support' => $this->supportSummary($branchIds),
            'technical' => $this->technicalSummary($branchIds),
            'noc' => $this->nocSummary($branchIds),
            'finance' => $this->financeSummary($branchIds),
            'installations_in_flight' => Installation::query()
                ->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))
                ->whereNotIn('status', [Installation::STATUS_ACCEPTED, Installation::STATUS_CANCELLED])
                ->count(),
            'task_backlog' => $this->taskQuery($branchIds)
                ->whereNotIn('status', [Task::STATUS_VERIFIED, Task::STATUS_CANCELLED, Task::STATUS_COMPLETED])
                ->count(),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function ticketQuery(?array $branchIds)
    {
        $q = Ticket::query();
        if ($branchIds !== null) {
            $q->whereIn('branch_id', $branchIds);
        }

        return $q;
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function taskQuery(?array $branchIds)
    {
        $q = Task::query();
        if ($branchIds !== null) {
            $q->whereIn('branch_id', $branchIds);
        }

        return $q;
    }
}
