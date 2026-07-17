<?php

namespace App\Services\Tickets;

use App\Events\TicketAssigned;
use App\Events\TicketOpened;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sla\SlaClockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketService
{
    public function __construct(
        private TicketNumberService $numbers,
        private TicketStatusTransitionService $transitions,
        private SlaClockService $sla,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Ticket
    {
        $this->validateRequired($data);

        return DB::transaction(function () use ($data, $actor) {
            $branchId = (int) $data['branch_id'];
            $ticket = Ticket::query()->create([
                'uuid' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'number' => $this->numbers->nextNumber($branchId),
                'type' => $data['type'],
                'channel' => $data['channel'] ?? ($data['type'] === Ticket::TYPE_WHATSAPP ? 'whatsapp' : 'internal'),
                'status' => Ticket::STATUS_OPEN,
                'priority' => $data['priority'] ?? 'normal',
                'category' => $data['category'] ?? null,
                'subcategory' => $data['subcategory'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'reporter_id' => $data['reporter_id'] ?? $actor?->id,
                'assignee_id' => $data['assignee_id'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            $this->sla->applyOnCreate($ticket);

            $this->audit->log('ticket.created', $ticket, null, $ticket->toArray(), $branchId);

            TicketOpened::dispatch($ticket->id, $branchId);

            if (! empty($data['assignee_id'])) {
                TicketAssigned::dispatch($ticket->id, $branchId, (int) $data['assignee_id']);
            }

            return $ticket->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $old = $ticket->only([
                'priority', 'category', 'subcategory', 'subject', 'description',
                'department_id', 'team_id', 'meta',
            ]);

            $ticket->fill(collect($data)->only([
                'priority', 'category', 'subcategory', 'subject', 'description',
                'department_id', 'team_id', 'meta',
            ])->all());
            $ticket->save();

            $this->audit->log('ticket.updated', $ticket, $old, $ticket->only(array_keys($old)), $ticket->branch_id);

            return $ticket->fresh();
        });
    }

    public function assign(Ticket $ticket, User $assignee, ?User $actor = null, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $assignee, $actor, $reason) {
            $old = $ticket->assignee_id;
            $ticket->assignee_id = $assignee->id;
            if ($ticket->status === Ticket::STATUS_OPEN) {
                $ticket->status = Ticket::STATUS_IN_PROGRESS;
            }
            if ($ticket->first_responded_at === null) {
                $ticket->first_responded_at = now();
            }
            $ticket->save();

            if ($old !== $assignee->id && $ticket->wasChanged('status')) {
                // status already updated inline for open→in_progress without full transition when assign
            }

            $this->audit->log('ticket.assigned', $ticket, ['assignee_id' => $old], [
                'assignee_id' => $assignee->id,
                'reason' => $reason,
                'actor_id' => $actor?->id,
            ], $ticket->branch_id, $reason);

            TicketAssigned::dispatch($ticket->id, (int) $ticket->branch_id, $assignee->id);

            return $ticket->fresh();
        });
    }

    public function addWatcher(Ticket $ticket, User $watcher, ?User $actor = null): Ticket
    {
        $ticket->watchers()->syncWithoutDetaching([$watcher->id]);
        $this->audit->log('ticket.watcher_added', $ticket, null, [
            'watcher_id' => $watcher->id,
            'actor_id' => $actor?->id,
        ], $ticket->branch_id);

        return $ticket->load('watchers');
    }

    public function resolve(Ticket $ticket, ?User $actor = null, ?string $notes = null, bool $customerConfirmed = false): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $notes, $customerConfirmed) {
            if ($notes) {
                $ticket->resolution_notes = $notes;
            }
            $ticket->customer_confirmed = $customerConfirmed;
            $ticket->save();

            return $this->transitions->transition($ticket, Ticket::STATUS_RESOLVED, $actor, $notes);
        });
    }

    public function close(Ticket $ticket, ?User $actor = null, ?string $reason = null): Ticket
    {
        if ($ticket->status !== Ticket::STATUS_RESOLVED) {
            $ticket = $this->resolve($ticket, $actor, $reason);
        }

        return $this->transitions->transition($ticket, Ticket::STATUS_CLOSED, $actor, $reason);
    }

    public function reopen(Ticket $ticket, ?User $actor = null, ?string $reason = null): Ticket
    {
        return $this->transitions->transition($ticket, Ticket::STATUS_OPEN, $actor, $reason);
    }

    public function linkMajorIncident(Ticket $ticket, Ticket $majorIncident, ?User $actor = null): Ticket
    {
        if ($ticket->id === $majorIncident->id) {
            throw new InvalidArgumentException('A ticket cannot be linked to itself as a major incident.');
        }

        $ticket->major_incident_id = $majorIncident->id;
        $ticket->save();

        $this->audit->log('ticket.major_incident_linked', $ticket, null, [
            'major_incident_id' => $majorIncident->id,
            'actor_id' => $actor?->id,
        ], $ticket->branch_id);

        return $ticket->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRequired(array $data): void
    {
        foreach (['branch_id', 'type', 'subject'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Ticket field [{$field}] is required.");
            }
        }

        $type = $data['type'];
        if (! in_array($type, [Ticket::TYPE_CUSTOMER, Ticket::TYPE_INTERNAL, Ticket::TYPE_WHATSAPP], true)) {
            throw new InvalidArgumentException("Invalid ticket type [{$type}].");
        }

        if ($type === Ticket::TYPE_CUSTOMER && empty($data['customer_id'])) {
            throw new InvalidArgumentException('Customer tickets require customer_id.');
        }

        if ($type === Ticket::TYPE_WHATSAPP && empty($data['customer_id']) && empty($data['conversation_id'])) {
            throw new InvalidArgumentException('WhatsApp tickets require customer_id or conversation_id.');
        }
    }
}
