<?php

namespace App\Services\Sla;

use App\Events\TicketEscalated;
use App\Models\Escalation;
use App\Models\Ticket;
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
        $rules = config('ticketing.escalation.rules', []);
        $created = [];

        $tickets = Ticket::query()
            ->whereNotIn('status', [
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
                Ticket::STATUS_CANCELLED,
            ])
            ->where('sla_paused', false)
            ->orderBy('id')
            ->limit($limit * 2)
            ->get();

        foreach ($tickets as $ticket) {
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

    /**
     * @param  array{code: string, level: string, minutes_after_due?: int, notify_role?: string}  $rule
     */
    public function maybeEscalate(Ticket $ticket, array $rule): ?Escalation
    {
        $code = (string) ($rule['code'] ?? '');
        if ($code === '') {
            return null;
        }

        $exists = Escalation::query()
            ->where('ticket_id', $ticket->id)
            ->where('rule_code', $code)
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return null;
        }

        $minutesAfter = (int) ($rule['minutes_after_due'] ?? 0);
        $dueAt = match ($code) {
            'first_response_overdue' => $ticket->first_responded_at ? null : $ticket->first_response_due_at,
            'resolution_overdue' => $ticket->resolution_due_at,
            default => $ticket->resolution_due_at,
        };

        if ($dueAt === null) {
            return null;
        }

        $threshold = $dueAt->copy()->addMinutes($minutesAfter);
        if ($threshold->isFuture()) {
            return null;
        }

        return $this->create($ticket, $code, (string) ($rule['level'] ?? 'l1'), $rule);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function create(Ticket $ticket, string $ruleCode, string $level, array $rule = []): Escalation
    {
        return DB::transaction(function () use ($ticket, $ruleCode, $level, $rule) {
            $notifyUserId = $ticket->assignee_id;
            $role = $rule['notify_role'] ?? null;
            if (is_string($role) && $role !== '') {
                $manager = User::query()->role($role)->whereHas('branches', fn ($q) => $q->where('branches.id', $ticket->branch_id))->first();
                $notifyUserId = $manager?->id ?? $notifyUserId;
            }

            $escalation = Escalation::query()->create([
                'branch_id' => $ticket->branch_id,
                'ticket_id' => $ticket->id,
                'rule_code' => $ruleCode,
                'level' => $level,
                'status' => 'open',
                'reason' => "Escalation rule [{$ruleCode}] triggered",
                'notified_user_id' => $notifyUserId,
                'escalated_at' => now(),
                'meta' => $rule,
            ]);

            if ($ticket->status !== Ticket::STATUS_ESCALATED) {
                $from = $ticket->status;
                $ticket->status = Ticket::STATUS_ESCALATED;
                $ticket->save();
                $ticket->transitions()->create([
                    'from_status' => $from,
                    'to_status' => Ticket::STATUS_ESCALATED,
                    'reason' => $escalation->reason,
                    'created_at' => now(),
                ]);
            }

            $this->audit->log('ticket.escalated', $ticket, null, [
                'escalation_id' => $escalation->id,
                'rule_code' => $ruleCode,
                'level' => $level,
            ], $ticket->branch_id);

            TicketEscalated::dispatch($ticket->id, (int) $ticket->branch_id, $escalation->id);

            return $escalation;
        });
    }
}
