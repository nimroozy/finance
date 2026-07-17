<?php

namespace App\Services\Timeline;

use App\Models\Customer;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkLog;

class CustomerTimelineService
{
    /**
     * Aggregate customer operational timeline with permission redaction.
     *
     * @return list<array{at: string, type: string, title: string, summary: ?string, meta: array}>
     */
    public function aggregate(Customer $customer, User $viewer, int $limit = 100): array
    {
        if (! $this->canViewCustomer($viewer, $customer)) {
            return [];
        }

        $canSeeInternal = $viewer->isSuperAdmin()
            || $viewer->isCentralFinanceAdmin()
            || $viewer->isBranchManager()
            || $viewer->hasRole(User::ROLE_AUDITOR);

        $events = collect();

        Ticket::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (Ticket $ticket) use ($events) {
                $events->push([
                    'at' => optional($ticket->created_at)->toIso8601String(),
                    'type' => 'ticket',
                    'title' => $ticket->number,
                    'summary' => $ticket->subject,
                    'meta' => [
                        'ticket_id' => $ticket->id,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                    ],
                ]);
            });

        Task::query()
            ->whereHas('ticket', fn ($q) => $q->where('customer_id', $customer->id))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (Task $task) use ($events, $canSeeInternal) {
                $events->push([
                    'at' => optional($task->created_at)->toIso8601String(),
                    'type' => 'task',
                    'title' => $task->number,
                    'summary' => $canSeeInternal ? $task->title : 'Task update',
                    'meta' => [
                        'task_id' => $task->id,
                        'status' => $task->status,
                        'work_type' => $canSeeInternal ? $task->work_type : null,
                    ],
                ]);
            });

        WorkLog::query()
            ->whereHas('ticket', fn ($q) => $q->where('customer_id', $customer->id))
            ->when(! $canSeeInternal, fn ($q) => $q->where('visibility', 'customer'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (WorkLog $log) use ($events, $canSeeInternal) {
                $events->push([
                    'at' => optional($log->created_at)->toIso8601String(),
                    'type' => 'work_log',
                    'title' => 'Work log',
                    'summary' => $canSeeInternal || $log->visibility === 'customer'
                        ? $log->body
                        : null,
                    'meta' => [
                        'work_log_id' => $log->id,
                        'visibility' => $log->visibility,
                    ],
                ]);
            });

        return $events
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }

    private function canViewCustomer(User $viewer, Customer $customer): bool
    {
        if ($viewer->isSuperAdmin() || $viewer->isCentralFinanceAdmin()) {
            return true;
        }

        if ($customer->branch_id === null) {
            return false;
        }

        return in_array((int) $customer->branch_id, $viewer->branchIds(), true);
    }
}
