<?php

namespace App\Services\Sla;

use App\Models\Tickets\SlaBreachEvent;
use App\Models\Tickets\SlaPolicy;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketSlaState;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class SlaClockService
{
    public function __construct(private AuditLogger $audit) {}

    public function applyOnCreate(Ticket $ticket, ?SlaPolicy $policy = null): Ticket
    {
        $policy ??= $this->resolvePolicy($ticket);
        if ($policy) {
            $ticket->sla_policy_id = $policy->id;
        }

        $responseMinutes = $policy?->response_minutes
            ?? (int) config('ticketing.sla.default_first_response_minutes', 60);
        $resolutionMinutes = $policy?->resolution_minutes
            ?? (int) config('ticketing.sla.default_resolution_minutes', 1440);

        $now = now();
        $ticket->response_due_at = $now->copy()->addMinutes($responseMinutes);
        $ticket->resolution_due_at = $now->copy()->addMinutes($resolutionMinutes);
        $ticket->save();

        TicketSlaState::query()->updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'response_state' => 'running',
                'resolution_state' => 'running',
                'response_remaining_seconds' => $responseMinutes * 60,
                'resolution_remaining_seconds' => $resolutionMinutes * 60,
                'paused_at' => null,
                'pause_total_seconds' => 0,
                'breached_at' => null,
            ]
        );

        return $ticket->fresh(['slaState']);
    }

    public function onStatusChange(Ticket $ticket, string $from, string $to): Ticket
    {
        $pauseStatuses = $this->pauseStatuses($ticket);

        $wasPaused = in_array($from, $pauseStatuses, true);
        $shouldPause = in_array($to, $pauseStatuses, true);

        if (! $wasPaused && $shouldPause) {
            return $this->pause($ticket);
        }

        if ($wasPaused && ! $shouldPause) {
            return $this->resume($ticket);
        }

        return $ticket;
    }

    public function pause(Ticket $ticket): Ticket
    {
        $state = $ticket->slaState ?? TicketSlaState::query()->firstOrCreate(
            ['ticket_id' => $ticket->id],
            ['response_state' => 'running', 'resolution_state' => 'running']
        );

        if ($state->paused_at !== null || $state->response_state === 'paused') {
            return $ticket;
        }

        $state->response_state = 'paused';
        $state->resolution_state = 'paused';
        $state->paused_at = now();
        $state->save();

        return $ticket->fresh(['slaState']);
    }

    public function resume(Ticket $ticket): Ticket
    {
        $state = $ticket->slaState;
        if (! $state || $state->paused_at === null) {
            if ($state) {
                $state->response_state = $ticket->first_response_at ? 'met' : 'running';
                $state->resolution_state = 'running';
                $state->save();
            }

            return $ticket->fresh(['slaState']);
        }

        $pausedSeconds = $state->paused_at->diffInSeconds(now());
        $state->pause_total_seconds = (int) $state->pause_total_seconds + $pausedSeconds;

        if ($ticket->response_due_at && ! $ticket->first_response_at) {
            $ticket->response_due_at = $ticket->response_due_at->copy()->addSeconds($pausedSeconds);
        }
        if ($ticket->resolution_due_at) {
            $ticket->resolution_due_at = $ticket->resolution_due_at->copy()->addSeconds($pausedSeconds);
        }
        $ticket->save();

        $state->response_state = $ticket->first_response_at ? 'met' : 'running';
        $state->resolution_state = 'running';
        $state->paused_at = null;
        $state->save();

        return $ticket->fresh(['slaState']);
    }

    /**
     * @return list<Ticket>
     */
    public function evaluateBreaches(?int $limit = null): array
    {
        $limit ??= (int) config('ticketing.sla.breach_check_batch', 200);
        $now = now();
        $breached = [];

        $tickets = Ticket::query()
            ->with('slaState')
            ->whereNotIn('status', [
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
                Ticket::STATUS_CANCELLED,
                Ticket::STATUS_VERIFICATION_PENDING,
            ])
            ->where(function ($qq) use ($now) {
                $qq->where(function ($q1) use ($now) {
                    $q1->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->where('response_due_at', '<', $now);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', $now);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tickets as $ticket) {
            $state = $ticket->slaState;
            if ($state && $state->paused_at !== null) {
                continue;
            }

            $breachType = null;
            $dueAt = null;
            if ($ticket->first_response_at === null && $ticket->response_due_at && $ticket->response_due_at->lt($now)) {
                $breachType = 'response';
                $dueAt = $ticket->response_due_at;
            } elseif ($ticket->resolution_due_at && $ticket->resolution_due_at->lt($now)) {
                $breachType = 'resolution';
                $dueAt = $ticket->resolution_due_at;
            }

            if (! $breachType) {
                continue;
            }

            if ($state) {
                $state->breached_at ??= $now;
                if ($breachType === 'response') {
                    $state->response_state = 'breached';
                } else {
                    $state->resolution_state = 'breached';
                }
                $state->save();
            }

            SlaBreachEvent::query()->create([
                'ticket_id' => $ticket->id,
                'sla_policy_id' => $ticket->sla_policy_id,
                'breach_type' => $breachType,
                'priority' => $ticket->priority,
                'due_at' => $dueAt,
                'breached_at' => $now,
                'overdue_seconds' => $dueAt ? $dueAt->diffInSeconds($now) : null,
            ]);

            $this->audit->log('ticket.sla_breached', $ticket, null, [
                'breach_type' => $breachType,
                'response_due_at' => optional($ticket->response_due_at)->toIso8601String(),
                'resolution_due_at' => optional($ticket->resolution_due_at)->toIso8601String(),
            ], $ticket->branch_id);

            $breached[] = $ticket;
        }

        return $breached;
    }

    /**
     * @return array{response_remaining_seconds: ?int, resolution_remaining_seconds: ?int, paused: bool, breached: bool}
     */
    public function remainingTimes(Ticket $ticket, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $state = $ticket->slaState;
        $pausedExtra = 0;
        if ($state?->paused_at) {
            $pausedExtra = $state->paused_at->diffInSeconds($at);
        }

        $responseRemaining = null;
        if ($ticket->response_due_at && ! $ticket->first_response_at) {
            $due = $ticket->response_due_at->copy()->addSeconds($pausedExtra);
            $responseRemaining = $at->diffInSeconds($due, false);
        }

        $resolutionRemaining = null;
        if ($ticket->resolution_due_at) {
            $due = $ticket->resolution_due_at->copy()->addSeconds($pausedExtra);
            $resolutionRemaining = $at->diffInSeconds($due, false);
        }

        return [
            'response_remaining_seconds' => $responseRemaining,
            'resolution_remaining_seconds' => $resolutionRemaining,
            'paused' => $state?->paused_at !== null,
            'breached' => $state?->breached_at !== null,
        ];
    }

    /**
     * @return list<string>
     */
    private function pauseStatuses(Ticket $ticket): array
    {
        $fromPolicy = $ticket->slaPolicy?->pause_statuses;
        if (is_array($fromPolicy) && $fromPolicy !== []) {
            return $fromPolicy;
        }

        return config('ticketing.sla.pause_statuses', [
            Ticket::STATUS_WAITING_CUSTOMER,
            Ticket::STATUS_WAITING_EQUIPMENT,
            Ticket::STATUS_SCHEDULED,
        ]);
    }

    private function resolvePolicy(Ticket $ticket): ?SlaPolicy
    {
        return SlaPolicy::query()
            ->where('is_active', true)
            ->where(function ($q) use ($ticket) {
                $q->where('branch_id', $ticket->branch_id)->orWhereNull('branch_id');
            })
            ->where(function ($q) use ($ticket) {
                $q->where('ticket_type_code', $ticket->type_code)->orWhereNull('ticket_type_code');
            })
            ->when($ticket->priority, fn ($q) => $q->where(function ($qq) use ($ticket) {
                $qq->where('priority', $ticket->priority)->orWhereNull('priority');
            }))
            ->orderByRaw('branch_id is null')
            ->orderByRaw('ticket_type_code is null')
            ->orderByRaw('priority is null')
            ->first();
    }
}
