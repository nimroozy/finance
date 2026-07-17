<?php

namespace App\Services\Tickets;

use App\Events\TicketClosed;
use App\Events\TicketEscalated;
use App\Events\TicketReopened;
use App\Events\TicketResolved;
use App\Events\TicketStatusChanged;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketStatusTransition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sla\SlaClockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketStatusTransitionService
{
    public function __construct(
        private SlaClockService $sla,
        private AuditLogger $audit,
    ) {}

    public function transition(
        Ticket $ticket,
        string $toStatus,
        ?User $actor = null,
        ?string $reason = null,
        ?string $source = null,
        ?string $comment = null,
    ): Ticket {
        $from = $ticket->status;
        if ($from === $toStatus) {
            return $ticket;
        }

        if (! $ticket->canTransitionTo($toStatus)) {
            throw new InvalidArgumentException("Invalid ticket status transition [{$from} → {$toStatus}].");
        }

        return DB::transaction(function () use ($ticket, $from, $toStatus, $actor, $reason, $source, $comment) {
            $ticket->status = $toStatus;
            $ticket->updated_by = $actor?->id;

            if ($toStatus === Ticket::STATUS_RESOLVED) {
                $ticket->resolved_at = now();
                if (is_string($reason) && $reason !== '' && empty($ticket->resolution_summary)) {
                    $ticket->resolution_summary = $reason;
                }
            }

            if ($toStatus === Ticket::STATUS_CLOSED) {
                $ticket->closed_at = now();
                $ticket->resolved_at ??= now();
            }

            if ($toStatus === Ticket::STATUS_REOPENED) {
                $ticket->reopened_count = (int) $ticket->reopened_count + 1;
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }

            if ($ticket->first_response_at === null
                && $actor
                && ! in_array($toStatus, [Ticket::STATUS_NEW, Ticket::STATUS_CANCELLED], true)
            ) {
                $ticket->first_response_at = now();
            }

            $ticket->save();

            TicketStatusTransition::query()->create([
                'ticket_id' => $ticket->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'user_id' => $actor?->id,
                'reason' => $reason,
                'source' => $source,
                'comment' => $comment,
                'created_at' => now(),
            ]);

            $this->sla->onStatusChange($ticket, $from, $toStatus);

            $this->audit->log('ticket.status_changed', $ticket, ['status' => $from], [
                'status' => $toStatus,
                'reason' => $reason,
                'source' => $source,
            ], $ticket->branch_id, $reason);

            TicketStatusChanged::dispatch($ticket->id, (int) $ticket->branch_id, $from, $toStatus);

            match ($toStatus) {
                Ticket::STATUS_ESCALATED => TicketEscalated::dispatch($ticket->id, (int) $ticket->branch_id, null),
                Ticket::STATUS_RESOLVED => TicketResolved::dispatch($ticket->id, (int) $ticket->branch_id),
                Ticket::STATUS_CLOSED => TicketClosed::dispatch($ticket->id, (int) $ticket->branch_id),
                Ticket::STATUS_REOPENED => TicketReopened::dispatch($ticket->id, (int) $ticket->branch_id),
                default => null,
            };

            return $ticket->fresh(['slaState']);
        });
    }
}
