<?php

namespace App\Services\Crm;

use App\Events\Crm\LeadStageChanged;
use App\Models\Crm\Lead;
use App\Models\Crm\LeadStageTransition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadPipelineService
{
    public function __construct(private AuditLogger $audit) {}

    public function transition(
        Lead $lead,
        string $toStage,
        ?User $actor = null,
        ?string $reason = null,
        ?string $notes = null,
    ): Lead {
        if (! in_array($toStage, Lead::PIPELINE_STAGES, true)) {
            throw new InvalidArgumentException("Unknown pipeline stage [{$toStage}].");
        }

        if (! $lead->canTransitionTo($toStage)) {
            throw new InvalidArgumentException("Invalid lead transition [{$lead->pipeline_stage} → {$toStage}].");
        }

        if ($toStage === Lead::STAGE_LOST && empty($reason) && empty($lead->lost_reason)) {
            throw new InvalidArgumentException('Lost reason is required when marking a lead as lost.');
        }

        return DB::transaction(function () use ($lead, $toStage, $actor, $reason, $notes) {
            $from = $lead->pipeline_stage;

            $lead->pipeline_stage = $toStage;
            if ($toStage === Lead::STAGE_LOST && $reason) {
                $lead->lost_reason = $reason;
            }
            if (in_array($toStage, [Lead::STAGE_ACTIVE_CUSTOMER, Lead::STAGE_INSTALLATION_REQUEST], true) && $reason) {
                $lead->win_reason = $lead->win_reason ?: $reason;
            }
            if ($actor && ! $lead->owner_id) {
                $lead->owner_id = $actor->id;
            }
            $lead->save();

            LeadStageTransition::query()->create([
                'lead_id' => $lead->id,
                'from_stage' => $from,
                'to_stage' => $toStage,
                'user_id' => $actor?->id,
                'reason' => $reason,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $this->audit->log('crm.lead.stage_changed', $lead, ['pipeline_stage' => $from], [
                'pipeline_stage' => $toStage,
                'actor_id' => $actor?->id,
            ], $lead->branch_id, $reason ?? $notes);

            LeadStageChanged::dispatch($lead->id, (int) $lead->branch_id, $from, $toStage);

            return $lead->fresh();
        });
    }
}
