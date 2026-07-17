<?php

namespace App\Services\Crm;

use App\Events\BusinessNotificationRequested;
use App\Events\Crm\FollowUpOverdue;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FollowUpService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(Lead $lead, array $data, ?User $actor = null): FollowUp
    {
        if (empty($data['due_at'])) {
            throw new InvalidArgumentException('Follow-up requires due_at.');
        }

        return DB::transaction(function () use ($lead, $data, $actor) {
            $followUp = FollowUp::query()->create([
                'lead_id' => $lead->id,
                'assigned_to' => $data['assigned_to'] ?? $lead->owner_id ?? $actor?->id,
                'due_at' => $data['due_at'],
                'status' => FollowUp::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $lead->next_follow_up_at = $data['due_at'];
            $lead->save();

            $this->audit->log('crm.follow_up.scheduled', $followUp, null, $followUp->toArray(), $lead->branch_id);

            return $followUp->fresh();
        });
    }

    public function complete(FollowUp $followUp, ?User $actor = null, ?string $notes = null): FollowUp
    {
        return DB::transaction(function () use ($followUp, $notes) {
            $old = $followUp->only(['status', 'notes']);
            $followUp->status = FollowUp::STATUS_DONE;
            if ($notes) {
                $followUp->notes = trim(($followUp->notes ? $followUp->notes."\n" : '').$notes);
            }
            $followUp->save();

            $this->audit->log(
                'crm.follow_up.completed',
                $followUp,
                $old,
                $followUp->only(['status', 'notes']),
                $followUp->lead?->branch_id,
            );

            return $followUp->fresh();
        });
    }

    public function cancel(FollowUp $followUp, ?User $actor = null): FollowUp
    {
        $followUp->status = FollowUp::STATUS_CANCELLED;
        $followUp->save();
        $this->audit->log('crm.follow_up.cancelled', $followUp, null, ['status' => FollowUp::STATUS_CANCELLED], $followUp->lead?->branch_id);

        return $followUp->fresh();
    }

    /**
     * Mark pending follow-ups past due as overdue and escalate stale ones.
     */
    public function evaluateOverdue(): int
    {
        $batch = (int) config('crm.follow_up.evaluate_batch', 200);
        $staleHours = (int) config('crm.follow_up.stale_hours', 24);
        $count = 0;

        FollowUp::query()
            ->with(['lead', 'assignee'])
            ->where('status', FollowUp::STATUS_PENDING)
            ->where('due_at', '<', now())
            ->orderBy('id')
            ->limit($batch)
            ->get()
            ->each(function (FollowUp $followUp) use (&$count, $staleHours) {
                $followUp->status = FollowUp::STATUS_OVERDUE;
                $followUp->save();
                $count++;

                FollowUpOverdue::dispatch($followUp->id, (int) ($followUp->lead?->branch_id ?? 0));

                $lead = $followUp->lead;
                if ($lead) {
                    BusinessNotificationRequested::dispatch('crm_follow_up_overdue', [
                        'branch_id' => $lead->branch_id,
                        'user_id' => $followUp->assigned_to,
                        'phone' => $followUp->assignee?->phone ?? $lead->whatsapp ?? $lead->phone,
                        'title' => 'CRM follow-up overdue',
                        'lead_id' => $lead->id,
                        'lead_number' => $lead->lead_number,
                        'follow_up_id' => $followUp->id,
                        'due_at' => optional($followUp->due_at)?->toIso8601String(),
                    ], ['in_app', 'whatsapp']);
                }

                $dueAt = $followUp->due_at;
                if ($dueAt && $dueAt->lte(now()->subHours($staleHours)) && $followUp->escalated_at === null) {
                    $this->escalate($followUp);
                }
            });

        FollowUp::query()
            ->with(['lead', 'assignee'])
            ->where('status', FollowUp::STATUS_OVERDUE)
            ->whereNull('escalated_at')
            ->where('due_at', '<=', now()->subHours($staleHours))
            ->orderBy('id')
            ->limit($batch)
            ->get()
            ->each(fn (FollowUp $f) => $this->escalate($f));

        return $count;
    }

    public function escalate(FollowUp $followUp): FollowUp
    {
        $followUp->escalated_at = now();
        if (! $followUp->reminder_sent_at) {
            $followUp->reminder_sent_at = now();
        }
        $followUp->save();

        $lead = $followUp->lead;
        BusinessNotificationRequested::dispatch('crm_follow_up_escalated', [
            'branch_id' => $lead?->branch_id,
            'user_id' => $followUp->assigned_to,
            'phone' => $followUp->assignee?->phone ?? $lead?->whatsapp ?? $lead?->phone,
            'title' => 'CRM follow-up escalated',
            'lead_id' => $lead?->id,
            'lead_number' => $lead?->lead_number,
            'follow_up_id' => $followUp->id,
        ], ['in_app', 'whatsapp']);

        $this->audit->log('crm.follow_up.escalated', $followUp, null, [
            'escalated_at' => $followUp->escalated_at?->toIso8601String(),
        ], $lead?->branch_id);

        return $followUp->fresh();
    }
}
