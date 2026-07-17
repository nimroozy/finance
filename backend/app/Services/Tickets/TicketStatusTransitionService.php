<?php

namespace App\Services\Tickets;

use App\Events\TicketClosed;
use App\Events\TicketEscalated;
use App\Events\TicketReopened;
use App\Events\TicketResolved;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\TicketStatusTransition;
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
        ?array $meta = null,
    ): Ticket {
        $from = $ticket->status;
        if ($from === $toStatus) {
            return $ticket;
        }

        $allowed = Ticket::ALLOWED_TRANSITIONS[$from] ?? [];
        if (! in_array($toStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid ticket status transition [{$from} → {$toStatus}].");
        }

        return DB::transaction(function () use ($ticket, $from, $toStatus, $actor, $reason, $meta) {
            $ticket->status = $toStatus;

            if ($toStatus === Ticket::STATUS_RESOLVED) {
                $ticket->resolved_at = now();
                if (is_string($reason) && $reason !== '' && empty($ticket->resolution_notes)) {
                    $ticket->resolution_notes = $reason;
                }
            }
            if ($toStatus === Ticket::STATUS_CLOSED) {
                $ticket->closed_at = now();
                $ticket->resolved_at ??= now();
            }
            if (in_array($from, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true)
                && in_array($toStatus, [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS], true)) {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
                $ticket->breached_at = null;
            }

            $ticket->save();

            TicketStatusTransition::create([
                'ticket_id' => $ticket->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'changed_by' => $actor?->id,
                'reason' => $reason,
                'meta' => $meta,
                'created_at' => now(),
            ]);

            $this->sla->onStatusChange($ticket, $from, $toStatus);

            $this->audit->log('ticket.status_changed', $ticket, ['status' => $from], [
                'status' => $toStatus,
                'reason' => $reason,
            ], $ticket->branch_id, $reason);

            TicketStatusChanged::dispatch($ticket->id, (int) $ticket->branch_id, $from, $toStatus);

            match ($toStatus) {
                Ticket::STATUS_ESCALATED => TicketEscalated::dispatch($ticket->id, (int) $ticket->branch_id, null),
                Ticket::STATUS_RESOLVED => TicketResolved::dispatch($ticket->id, (int) $ticket->branch_id),
                Ticket::STATUS_CLOSED => TicketClosed::dispatch($ticket->id, (int) $ticket->branch_id),
                Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS => in_array($from, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED], true)
                    ? TicketReopened::dispatch($ticket->id, (int) $ticket->branch_id)
                    : null,
                default => null,
            };

            return $ticket->fresh();
        });
    }
}
