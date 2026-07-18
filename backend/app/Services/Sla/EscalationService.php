<?php

namespace App\Services\Sla;

use App\Events\TicketEscalated;
use App\Models\Tickets\Escalation;
use App\Models\Tickets\EscalationRule;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketStatusTransition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return list<Escalation>
     */
    public function evaluate(?int $limit = null): array
    {
        $limit ??= (int) config('ticketing.escalation.batch', 100);
        $created = [];

        $rules = EscalationRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($rules->isEmpty()) {
            foreach (config('ticketing.escalation.rules', []) as $rule) {
                $escalation = $this->maybeEscalateFromConfig($rule, $limit);
                if ($escalation) {
                    $created[] = $escalation;
                    if (count($created) >= $limit) {
                        return $created;
                    }
                }
            }

            return $created;
        }

        $tickets = Ticket::query()
            ->whereNotIn('status', [
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
                Ticket::STATUS_CANCELLED,
                Ticket::STATUS_VERIFICATION_PENDING,
            ])
            ->with('slaState')
            ->orderBy('id')
            ->limit($limit * 3)
            ->get();

        foreach ($tickets as $ticket) {
            if ($ticket->slaState?->paused_at) {
                continue;
            }
            foreach ($rules as $rule) {
                $escalation = $this->maybeEscalate($ticket, $rule);
                if ($escalation) {
                    $created[] = $escalation;
                    if (count($created) >= $limit) {
                        return $created;
                    }
                }
            }
        }

        return $created;
    }

    public function maybeEscalate(Ticket $ticket, EscalationRule $rule): ?Escalation
    {
        if ($rule->branch_id && (int) $rule->branch_id !== (int) $ticket->branch_id) {
            return null;
        }
        if ($rule->ticket_type_code && $rule->ticket_type_code !== $ticket->type_code) {
            return null;
        }
        if ($rule->priority && $rule->priority !== $ticket->priority) {
            return null;
        }
        if ($rule->from_status && $rule->from_status !== $ticket->status) {
            return null;
        }

        $exists = Escalation::query()
            ->where('ticket_id', $ticket->id)
            ->where('escalation_rule_id', $rule->id)
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return null;
        }

        $minutes = (int) ($rule->trigger_after_minutes ?? 0);
        $dueAt = $ticket->resolution_due_at ?? $ticket->response_due_at;
        if ($dueAt === null) {
            return null;
        }

        $threshold = $dueAt->copy()->addMinutes($minutes);
        if ($threshold->isFuture()) {
            return null;
        }

        return $this->createFromRule($ticket, $rule);
    }

    /**
     * @param  array{code?: string, level?: string, minutes_after_due?: int, notify_role?: string}  $rule
     */
    private function maybeEscalateFromConfig(array $rule, int $remaining): ?Escalation
    {
        $code = (string) ($rule['code'] ?? '');
        if ($code === '') {
            return null;
        }

        $ticket = Ticket::query()
            ->whereNotIn('status', [
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
                Ticket::STATUS_CANCELLED,
            ])
            ->whereDoesntHave('escalations', fn ($q) => $q->where('status', 'open')->where('meta->rule_code', $code))
            ->orderBy('id')
            ->first();

        if (! $ticket) {
            return null;
        }

        $minutesAfter = (int) ($rule['minutes_after_due'] ?? 0);
        $dueAt = match ($code) {
            'first_response_overdue' => $ticket->first_response_at ? null : $ticket->response_due_at,
            default => $ticket->resolution_due_at,
        };

        if ($dueAt === null || $dueAt->copy()->addMinutes($minutesAfter)->isFuture()) {
            return null;
        }

        return $this->create($ticket, $code, (string) ($rule['level'] ?? 'l1'), $rule);
    }

    public function createFromRule(Ticket $ticket, EscalationRule $rule, ?User $actor = null): Escalation
    {
        return DB::transaction(function () use ($ticket, $rule, $actor) {
            $toUserId = $rule->escalate_to_user_id;
            if (! $toUserId && $rule->escalate_to_role) {
                $toUserId = User::query()
                    ->role($rule->escalate_to_role)
                    ->whereHas('branches', fn ($q) => $q->where('branches.id', $ticket->branch_id))
                    ->value('id');
            }

            $escalation = Escalation::query()->create([
                'ticket_id' => $ticket->id,
                'escalation_rule_id' => $rule->id,
                'level' => (string) ($rule->sort_order ?: '1'),
                'status' => 'open',
                'from_user_id' => $actor?->id ?? $ticket->primary_assignee_id,
                'to_user_id' => $toUserId,
                'to_department_code' => $rule->escalate_to_department_code,
                'reason' => "Escalation rule [{$rule->name}] triggered",
                'escalated_at' => now(),
                'meta' => ['rule_id' => $rule->id],
            ]);

            $this->markTicketEscalated($ticket, $escalation->reason, $actor);

            $this->audit->log('ticket.escalated', $ticket, null, [
                'escalation_id' => $escalation->id,
                'escalation_rule_id' => $rule->id,
            ], $ticket->branch_id);

            TicketEscalated::dispatch($ticket->id, (int) $ticket->branch_id, $escalation->id);

            return $escalation;
        });
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function create(Ticket $ticket, string $ruleCode, string $level, array $rule = [], ?User $actor = null): Escalation
    {
        return DB::transaction(function () use ($ticket, $ruleCode, $level, $rule, $actor) {
            $notifyUserId = $ticket->primary_assignee_id;
            $role = $rule['notify_role'] ?? null;
            if (is_string($role) && $role !== '') {
                $manager = User::query()
                    ->role($role)
                    ->whereHas('branches', fn ($q) => $q->where('branches.id', $ticket->branch_id))
                    ->first();
                $notifyUserId = $manager?->id ?? $notifyUserId;
            }

            $escalation = Escalation::query()->create([
                'ticket_id' => $ticket->id,
                'escalation_rule_id' => null,
                'level' => $level,
                'status' => 'open',
                'from_user_id' => $actor?->id,
                'to_user_id' => $notifyUserId,
                'reason' => "Escalation rule [{$ruleCode}] triggered",
                'escalated_at' => now(),
                'meta' => array_merge($rule, ['rule_code' => $ruleCode]),
            ]);

            $this->markTicketEscalated($ticket, $escalation->reason, $actor);

            $this->audit->log('ticket.escalated', $ticket, null, [
                'escalation_id' => $escalation->id,
                'rule_code' => $ruleCode,
                'level' => $level,
            ], $ticket->branch_id);

            TicketEscalated::dispatch($ticket->id, (int) $ticket->branch_id, $escalation->id);

            return $escalation;
        });
    }

    public function acknowledge(Escalation $escalation, ?User $actor = null): Escalation
    {
        $escalation->status = 'acknowledged';
        $escalation->acknowledged_at = now();
        $escalation->save();

        $this->audit->log('escalation.acknowledged', $escalation, null, [
            'actor_id' => $actor?->id,
        ], $escalation->ticket?->branch_id);

        return $escalation->fresh();
    }

    private function markTicketEscalated(Ticket $ticket, ?string $reason, ?User $actor): void
    {
        if ($ticket->status === Ticket::STATUS_ESCALATED) {
            return;
        }

        if (! $ticket->canTransitionTo(Ticket::STATUS_ESCALATED)) {
            return;
        }

        $from = $ticket->status;
        $ticket->status = Ticket::STATUS_ESCALATED;
        $ticket->updated_by = $actor?->id;
        $ticket->save();

        TicketStatusTransition::query()->create([
            'ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => Ticket::STATUS_ESCALATED,
            'user_id' => $actor?->id,
            'reason' => $reason,
            'source' => 'escalation',
            'created_at' => now(),
        ]);
    }
}
