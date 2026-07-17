<?php

namespace App\Services\Timeline;

use App\Models\Customer;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\WorkLog;
use App\Models\User;

class CustomerTimelineService
{
    /**
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
                    'title' => $ticket->ticket_number,
                    'summary' => $ticket->subject,
                    'meta' => [
                        'ticket_id' => $ticket->id,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                    ],
                ]);
            });

        Task::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                    ->orWhereHas('ticket', fn ($qq) => $qq->where('customer_id', $customer->id));
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (Task $task) use ($events, $canSeeInternal) {
                $events->push([
                    'at' => optional($task->created_at)->toIso8601String(),
                    'type' => 'task',
                    'title' => $task->task_number,
                    'summary' => $canSeeInternal ? $task->title : 'Task update',
                    'meta' => [
                        'task_id' => $task->id,
                        'status' => $task->status,
                        'type' => $canSeeInternal ? $task->type : null,
                    ],
                ]);
            });

        WorkLog::query()
            ->whereHas('ticket', fn ($q) => $q->where('customer_id', $customer->id))
            ->when(! $canSeeInternal, fn ($q) => $q->whereNotNull('customer_visible_note'))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (WorkLog $log) use ($events, $canSeeInternal) {
                $events->push([
                    'at' => optional($log->created_at)->toIso8601String(),
                    'type' => 'work_log',
                    'title' => 'Work log',
                    'summary' => $canSeeInternal
                        ? ($log->internal_note ?: $log->customer_visible_note)
                        : $log->customer_visible_note,
                    'meta' => [
                        'work_log_id' => $log->id,
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
