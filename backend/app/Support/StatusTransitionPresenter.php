<?php

namespace App\Support;

use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

class StatusTransitionPresenter
{
    /**
     * @return list<array{status: string, label: string, requires_reason: bool, required_fields: list<string>, permission: string|null}>
     */
    public function forTicket(Ticket $ticket, ?User $user = null): array
    {
        $allowed = Ticket::allowedTransitions()[$ticket->status] ?? [];
        $out = [];

        foreach ($allowed as $status) {
            $permission = $this->ticketPermissionFor($status);
            if ($user && $permission && ! $user->can($permission) && ! $user->isSuperAdmin()) {
                continue;
            }

            $out[] = [
                'status' => $status,
                'label' => $this->label($status),
                'requires_reason' => in_array($status, [
                    Ticket::STATUS_CANCELLED,
                    Ticket::STATUS_REOPENED,
                    Ticket::STATUS_ESCALATED,
                ], true),
                'required_fields' => $status === Ticket::STATUS_RESOLVED
                    ? ['resolution_summary']
                    : [],
                'permission' => $permission,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{status: string, label: string, requires_reason: bool, required_fields: list<string>, permission: string|null}>
     */
    public function forTask(Task $task, ?User $user = null): array
    {
        $allowed = Task::allowedTransitions()[$task->status] ?? [];
        $out = [];

        foreach ($allowed as $status) {
            $permission = $this->taskPermissionFor($status);
            if ($user && $permission && ! $user->can($permission) && ! $user->isSuperAdmin()) {
                // Still allow assignee accept/complete paths when they hold those perms.
                continue;
            }

            $out[] = [
                'status' => $status,
                'label' => $this->label($status),
                'requires_reason' => in_array($status, [
                    Task::STATUS_REJECTED,
                    Task::STATUS_BLOCKED,
                    Task::STATUS_CANCELLED,
                    Task::STATUS_FAILED,
                ], true),
                'required_fields' => [],
                'permission' => $permission,
            ];
        }

        return $out;
    }

    public function label(string $status): string
    {
        return Str::of($status)->replace('_', ' ')->title()->toString();
    }

    private function ticketPermissionFor(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_RESOLVED => 'tickets.resolve',
            Ticket::STATUS_CLOSED => 'tickets.close',
            Ticket::STATUS_REOPENED => 'tickets.reopen',
            default => 'tickets.update',
        };
    }

    private function taskPermissionFor(string $status): string
    {
        return match ($status) {
            Task::STATUS_OFFERED => 'tasks.assign',
            Task::STATUS_ACCEPTED, Task::STATUS_REJECTED => 'tasks.accept',
            Task::STATUS_TRAVELLING,
            Task::STATUS_ARRIVED,
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
            Task::STATUS_BLOCKED,
            Task::STATUS_WAITING => 'tasks.complete',
            Task::STATUS_APPROVED, Task::STATUS_VERIFICATION_PENDING => 'tasks.verify',
            Task::STATUS_CANCELLED => 'tasks.cancel',
            default => 'tasks.view',
        };
    }
}
