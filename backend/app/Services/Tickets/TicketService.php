<?php

namespace App\Services\Tickets;

use App\Events\TicketAssigned;
use App\Events\TicketOpened;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sla\SlaClockService;
use Illuminate\Support\Facades\DB;
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
        $type = $this->validateCreate($data);

        return DB::transaction(function () use ($data, $actor, $type) {
            $branchId = (int) $data['branch_id'];
            $priority = $data['priority'] ?? $type->default_priority ?? Ticket::PRIORITY_NORMAL;

            $ticket = Ticket::query()->create([
                'ticket_number' => $this->numbers->nextNumber($branchId),
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_number' => $data['customer_number'] ?? null,
                'source' => $data['source'] ?? Ticket::SOURCE_INTERNAL,
                'type_code' => $type->code,
                'category' => $data['category'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'priority' => $priority,
                'severity' => $data['severity'] ?? null,
                'impact' => $data['impact'] ?? null,
                'urgency' => $data['urgency'] ?? null,
                'status' => Ticket::STATUS_NEW,
                'assigned_department_id' => $data['assigned_department_id'] ?? null,
                'assigned_team_id' => $data['assigned_team_id'] ?? null,
                'primary_assignee_id' => $data['primary_assignee_id'] ?? null,
                'sla_policy_id' => $data['sla_policy_id'] ?? $type->sla_policy_id,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_location' => $data['customer_location'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'related_tower' => $data['related_tower'] ?? null,
                'related_site' => $data['related_site'] ?? null,
                'related_radius_account' => $data['related_radius_account'] ?? null,
                'related_invoice_id' => $data['related_invoice_id'] ?? null,
                'related_payment_id' => $data['related_payment_id'] ?? null,
                'related_installation_id' => $data['related_installation_id'] ?? null,
                'whatsapp_conversation_id' => $data['whatsapp_conversation_id'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'tags' => $data['tags'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'customer_visible_notes' => $data['customer_visible_notes'] ?? null,
                'created_by' => $actor?->id ?? ($data['created_by'] ?? null),
                'updated_by' => $actor?->id ?? ($data['updated_by'] ?? null),
            ]);

            $this->sla->applyOnCreate($ticket);

            $this->audit->log('ticket.created', $ticket, null, $ticket->toArray(), $branchId);

            TicketOpened::dispatch($ticket->id, $branchId);

            if (! empty($data['primary_assignee_id'])) {
                TicketAssigned::dispatch($ticket->id, $branchId, (int) $data['primary_assignee_id']);
            }

            return $ticket->fresh(['slaState']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Ticket $ticket, array $data, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $fields = [
                'priority', 'severity', 'impact', 'urgency', 'category', 'subject', 'description',
                'assigned_department_id', 'assigned_team_id', 'customer_phone', 'customer_location',
                'gps_lat', 'gps_lng', 'related_tower', 'related_site', 'related_radius_account',
                'related_invoice_id', 'related_payment_id', 'related_installation_id',
                'tags', 'internal_notes', 'customer_visible_notes', 'resolution_code',
                'resolution_summary', 'customer_confirmation',
            ];

            $old = $ticket->only($fields);
            $ticket->fill(collect($data)->only($fields)->all());
            $ticket->updated_by = $actor?->id;
            $ticket->save();

            $this->audit->log('ticket.updated', $ticket, $old, $ticket->only(array_keys($old)), $ticket->branch_id);

            return $ticket->fresh();
        });
    }

    public function assign(Ticket $ticket, User $assignee, ?User $actor = null, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $assignee, $actor, $reason) {
            $old = $ticket->primary_assignee_id;
            $ticket->primary_assignee_id = $assignee->id;
            $ticket->updated_by = $actor?->id;

            if ($ticket->status === Ticket::STATUS_NEW) {
                $ticket->status = Ticket::STATUS_TRIAGED;
                $ticket->save();
                $this->transitions->transition($ticket, Ticket::STATUS_ASSIGNED, $actor, $reason, 'assign');
            } elseif ($ticket->status === Ticket::STATUS_TRIAGED) {
                $ticket->save();
                $this->transitions->transition($ticket, Ticket::STATUS_ASSIGNED, $actor, $reason, 'assign');
            } else {
                $ticket->save();
            }

            if ($ticket->first_response_at === null) {
                $ticket->first_response_at = now();
                $ticket->save();
            }

            $this->audit->log('ticket.assigned', $ticket, ['primary_assignee_id' => $old], [
                'primary_assignee_id' => $assignee->id,
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

    public function removeWatcher(Ticket $ticket, User $watcher, ?User $actor = null): Ticket
    {
        $ticket->watchers()->detach($watcher->id);
        $this->audit->log('ticket.watcher_removed', $ticket, null, [
            'watcher_id' => $watcher->id,
            'actor_id' => $actor?->id,
        ], $ticket->branch_id);

        return $ticket->load('watchers');
    }

    public function resolve(Ticket $ticket, ?User $actor = null, ?string $summary = null, bool $customerConfirmed = false): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $summary, $customerConfirmed) {
            if ($summary) {
                $ticket->resolution_summary = $summary;
            }
            $ticket->customer_confirmation = $customerConfirmed;
            if ($customerConfirmed) {
                $ticket->customer_confirmed_at = now();
            }
            $ticket->updated_by = $actor?->id;
            $ticket->save();

            return $this->transitions->transition($ticket, Ticket::STATUS_RESOLVED, $actor, $summary, 'resolve');
        });
    }

    public function close(Ticket $ticket, ?User $actor = null, ?string $reason = null): Ticket
    {
        if (! in_array($ticket->status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_VERIFICATION_PENDING], true)) {
            $ticket = $this->resolve($ticket, $actor, $reason);
            if ($ticket->canTransitionTo(Ticket::STATUS_VERIFICATION_PENDING)) {
                $ticket = $this->transitions->transition($ticket, Ticket::STATUS_VERIFICATION_PENDING, $actor, $reason, 'close');
            }
        }

        if ($ticket->status === Ticket::STATUS_RESOLVED && $ticket->canTransitionTo(Ticket::STATUS_VERIFICATION_PENDING)) {
            $ticket = $this->transitions->transition($ticket, Ticket::STATUS_VERIFICATION_PENDING, $actor, $reason, 'close');
        }

        return $this->transitions->transition($ticket, Ticket::STATUS_CLOSED, $actor, $reason, 'close');
    }

    public function reopen(Ticket $ticket, ?User $actor = null, ?string $reason = null): Ticket
    {
        return $this->transitions->transition($ticket, Ticket::STATUS_REOPENED, $actor, $reason, 'reopen');
    }

    public function transition(Ticket $ticket, string $toStatus, ?User $actor = null, ?string $reason = null, ?string $source = null, ?string $comment = null): Ticket
    {
        return $this->transitions->transition($ticket, $toStatus, $actor, $reason, $source, $comment);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateCreate(array $data): TicketType
    {
        foreach (['branch_id', 'type_code', 'subject'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Ticket field [{$field}] is required.");
            }
        }

        if (! empty($data['source']) && ! in_array($data['source'], Ticket::SOURCES, true)) {
            throw new InvalidArgumentException("Invalid ticket source [{$data['source']}].");
        }

        if (! empty($data['priority']) && ! in_array($data['priority'], Ticket::PRIORITIES, true)) {
            throw new InvalidArgumentException("Invalid ticket priority [{$data['priority']}].");
        }

        if (! empty($data['severity']) && ! in_array($data['severity'], Ticket::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid ticket severity [{$data['severity']}].");
        }

        $type = TicketType::query()
            ->where('code', $data['type_code'])
            ->where('is_active', true)
            ->first();

        if (! $type) {
            throw new InvalidArgumentException("Unknown or inactive ticket type_code [{$data['type_code']}].");
        }

        if ($type->requires_customer && empty($data['customer_id'])) {
            throw new InvalidArgumentException("Ticket type [{$type->code}] requires customer_id.");
        }

        return $type;
    }
}
