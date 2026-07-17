<?php

namespace App\Services\Sla;

use App\Models\SlaPolicy;
use App\Models\Ticket;
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

        $firstMinutes = $policy?->first_response_minutes
            ?? (int) config('ticketing.sla.default_first_response_minutes', 60);
        $resolutionMinutes = $policy?->resolution_minutes
            ?? (int) config('ticketing.sla.default_resolution_minutes', 1440);

        $now = now();
        $ticket->first_response_due_at = $now->copy()->addMinutes($firstMinutes);
        $ticket->resolution_due_at = $now->copy()->addMinutes($resolutionMinutes);
        $ticket->sla_paused = false;
        $ticket->sla_paused_at = null;
        $ticket->sla_paused_seconds = 0;
        $ticket->breached_at = null;
        $ticket->save();

        return $ticket;
    }

    public function onStatusChange(Ticket $ticket, string $from, string $to): Ticket
    {
        $pauseStatuses = config('ticketing.sla.pause_statuses', [
            'waiting_customer', 'waiting_equipment', 'scheduled',
        ]);

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
        if ($ticket->sla_paused) {
            return $ticket;
        }

        $ticket->sla_paused = true;
        $ticket->sla_paused_at = now();
        $ticket->save();

        return $ticket;
    }

    public function resume(Ticket $ticket): Ticket
    {
        if (! $ticket->sla_paused || $ticket->sla_paused_at === null) {
            $ticket->sla_paused = false;
            $ticket->sla_paused_at = null;
            $ticket->save();

            return $ticket;
        }

        $pausedSeconds = $ticket->sla_paused_at->diffInSeconds(now());
        $ticket->sla_paused_seconds = (int) $ticket->sla_paused_seconds + $pausedSeconds;

        if ($ticket->first_response_due_at && ! $ticket->first_responded_at) {
            $ticket->first_response_due_at = $ticket->first_response_due_at->copy()->addSeconds($pausedSeconds);
        }
        if ($ticket->resolution_due_at) {
            $ticket->resolution_due_at = $ticket->resolution_due_at->copy()->addSeconds($pausedSeconds);
        }

        $ticket->sla_paused = false;
        $ticket->sla_paused_at = null;
        $ticket->save();

        return $ticket;
    }

    /**
     * @return list<Ticket>
     */
    public function evaluateBreaches(?int $limit = null): array
    {
        $limit ??= (int) config('ticketing.sla.breach_check_batch', 200);
        $now = now();
        $breached = [];

        $q = Ticket::query()
            ->whereNull('breached_at')
            ->whereNotIn('status', [
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
                Ticket::STATUS_CANCELLED,
            ])
            ->where('sla_paused', false)
            ->where(function ($qq) use ($now) {
                $qq->where(function ($q1) use ($now) {
                    $q1->whereNull('first_responded_at')
                        ->whereNotNull('first_response_due_at')
                        ->where('first_response_due_at', '<', $now);
                })->orWhere(function ($q2) use ($now) {
                    $q2->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', $now);
                });
            })
            ->orderBy('id')
            ->limit($limit);

        foreach ($q->get() as $ticket) {
            $ticket->breached_at = $now;
            $ticket->save();
            $this->audit->log('ticket.sla_breached', $ticket, null, [
                'first_response_due_at' => optional($ticket->first_response_due_at)->toIso8601String(),
                'resolution_due_at' => optional($ticket->resolution_due_at)->toIso8601String(),
            ], $ticket->branch_id);
            $breached[] = $ticket;
        }

        return $breached;
    }

    /**
     * @return array{first_response_remaining_seconds: ?int, resolution_remaining_seconds: ?int, paused: bool, breached: bool}
     */
    public function remainingTimes(Ticket $ticket, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : now();
        $pausedExtra = 0;
        if ($ticket->sla_paused && $ticket->sla_paused_at) {
            $pausedExtra = $ticket->sla_paused_at->diffInSeconds($at);
        }

        $firstRemaining = null;
        if ($ticket->first_response_due_at && ! $ticket->first_responded_at) {
            $due = $ticket->first_response_due_at->copy()->addSeconds($pausedExtra);
            $firstRemaining = $at->diffInSeconds($due, false);
        }

        $resolutionRemaining = null;
        if ($ticket->resolution_due_at) {
            $due = $ticket->resolution_due_at->copy()->addSeconds($pausedExtra);
            $resolutionRemaining = $at->diffInSeconds($due, false);
        }

        return [
            'first_response_remaining_seconds' => $firstRemaining,
            'resolution_remaining_seconds' => $resolutionRemaining,
            'paused' => (bool) $ticket->sla_paused,
            'breached' => $ticket->breached_at !== null,
        ];
    }

    private function resolvePolicy(Ticket $ticket): ?SlaPolicy
    {
        return SlaPolicy::query()
            ->where('is_active', true)
            ->where(function ($q) use ($ticket) {
                $q->where('branch_id', $ticket->branch_id)->orWhereNull('branch_id');
            })
            ->when($ticket->priority, fn ($q) => $q->where(function ($qq) use ($ticket) {
                $qq->where('priority', $ticket->priority)->orWhereNull('priority');
            }))
            ->orderByDesc('branch_id')
            ->orderByDesc('priority')
            ->first();
    }
}
