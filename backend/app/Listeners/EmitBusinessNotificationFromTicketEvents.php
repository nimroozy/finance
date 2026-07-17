<?php

namespace App\Listeners;

use App\Events\AttachmentUploaded;
use App\Events\BusinessNotificationRequested;
use App\Events\InstallationStatusChanged;
use App\Events\TaskAccepted;
use App\Events\TaskBlocked;
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskOffered;
use App\Events\TaskReassigned;
use App\Events\TaskRejected;
use App\Events\TaskStarted;
use App\Events\TaskVerified;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketEscalated;
use App\Events\TicketOpened;
use App\Events\TicketReopened;
use App\Events\TicketResolved;
use App\Events\TicketStatusChanged;
use App\Events\WorkLogAdded;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class EmitBusinessNotificationFromTicketEvents implements ShouldQueueAfterCommit
{
    public function onDomainEvent(object $event): void
    {
        $mapped = $this->map($event);
        if ($mapped === null) {
            return;
        }

        BusinessNotificationRequested::dispatch($mapped['event_type'], $mapped['payload'], $mapped['channels']);
    }

    /**
     * @return array{event_type: string, payload: array, channels: list<string>}|null
     */
    private function map(object $event): ?array
    {
        return match (true) {
            $event instanceof TicketOpened => $this->ticketPayload('ticket_opened', $event->ticketId, $event->branchId, 'Ticket opened'),
            $event instanceof TicketAssigned => $this->ticketPayload('ticket_assigned', $event->ticketId, $event->branchId, 'Ticket assigned', $event->assigneeId),
            $event instanceof TicketStatusChanged => $this->ticketPayload('ticket_status_changed', $event->ticketId, $event->branchId, "Ticket status {$event->fromStatus} → {$event->toStatus}"),
            $event instanceof TicketEscalated => $this->ticketPayload('ticket_escalated', $event->ticketId, $event->branchId, 'Ticket escalated'),
            $event instanceof TicketResolved => $this->ticketPayload('ticket_resolved', $event->ticketId, $event->branchId, 'Ticket resolved'),
            $event instanceof TicketClosed => $this->ticketPayload('ticket_closed', $event->ticketId, $event->branchId, 'Ticket closed'),
            $event instanceof TicketReopened => $this->ticketPayload('ticket_reopened', $event->ticketId, $event->branchId, 'Ticket reopened'),
            $event instanceof TaskCreated => $this->taskPayload('task_created', $event->taskId, $event->branchId, 'Task created'),
            $event instanceof TaskOffered => $this->taskPayload('task_offered', $event->taskId, $event->branchId, 'Task offered', $event->userId),
            $event instanceof TaskAccepted => $this->taskPayload('task_accepted', $event->taskId, $event->branchId, 'Task accepted', $event->userId),
            $event instanceof TaskRejected => $this->taskPayload('task_rejected', $event->taskId, $event->branchId, 'Task rejected', $event->userId),
            $event instanceof TaskReassigned => $this->taskPayload('task_reassigned', $event->taskId, $event->branchId, 'Task reassigned', $event->userId),
            $event instanceof TaskStarted => $this->taskPayload('task_started', $event->taskId, $event->branchId, 'Task started'),
            $event instanceof TaskCompleted => $this->taskPayload('task_completed', $event->taskId, $event->branchId, 'Task completed'),
            $event instanceof TaskVerified => $this->taskPayload('task_verified', $event->taskId, $event->branchId, 'Task verified'),
            $event instanceof TaskBlocked => $this->taskPayload('task_blocked', $event->taskId, $event->branchId, 'Task blocked: '.($event->reason ?? '')),
            $event instanceof InstallationStatusChanged => [
                'event_type' => 'installation_status_changed',
                'payload' => [
                    'branch_id' => $event->branchId,
                    'installation_id' => $event->installationId,
                    'from_status' => $event->fromStatus,
                    'to_status' => $event->toStatus,
                    'title' => 'Installation status changed',
                    'body' => "{$event->fromStatus} → {$event->toStatus}",
                ],
                'channels' => ['in_app', 'whatsapp'],
            ],
            $event instanceof WorkLogAdded => [
                'event_type' => 'work_log_added',
                'payload' => [
                    'branch_id' => $event->branchId,
                    'work_log_id' => $event->workLogId,
                    'ticket_id' => $event->ticketId,
                    'task_id' => $event->taskId,
                    'title' => 'Work log added',
                ],
                'channels' => ['in_app'],
            ],
            $event instanceof AttachmentUploaded => [
                'event_type' => 'attachment_uploaded',
                'payload' => [
                    'branch_id' => $event->branchId,
                    'attachment_id' => $event->attachmentId,
                    'attachable_type' => $event->attachableType,
                    'attachable_id' => $event->attachableId,
                    'title' => 'Attachment uploaded',
                ],
                'channels' => ['in_app'],
            ],
            default => null,
        };
    }

    /**
     * @return array{event_type: string, payload: array, channels: list<string>}
     */
    private function ticketPayload(string $type, int $ticketId, int $branchId, string $title, ?int $userId = null): array
    {
        $ticket = Ticket::query()->find($ticketId);
        $userId ??= $ticket?->assignee_id;
        $phone = $userId ? User::query()->find($userId)?->phone : null;

        return [
            'event_type' => $type,
            'payload' => [
                'branch_id' => $branchId,
                'ticket_id' => $ticketId,
                'ticket_number' => $ticket?->number,
                'user_id' => $userId,
                'phone' => $phone,
                'customer_id' => $ticket?->customer_id,
                'title' => $title,
                'body' => $ticket?->subject,
            ],
            'channels' => ['in_app', 'whatsapp'],
        ];
    }

    /**
     * @return array{event_type: string, payload: array, channels: list<string>}
     */
    private function taskPayload(string $type, int $taskId, int $branchId, string $title, ?int $userId = null): array
    {
        $task = Task::query()->find($taskId);
        $userId ??= $task?->assignee_id ?? $task?->offered_to_id;
        $phone = $userId ? User::query()->find($userId)?->phone : null;

        return [
            'event_type' => $type,
            'payload' => [
                'branch_id' => $branchId,
                'task_id' => $taskId,
                'task_number' => $task?->number,
                'ticket_id' => $task?->ticket_id,
                'user_id' => $userId,
                'phone' => $phone,
                'title' => $title,
                'body' => $task?->title,
            ],
            'channels' => ['in_app', 'whatsapp'],
        ];
    }
}
