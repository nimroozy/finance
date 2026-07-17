<?php

namespace App\Services\Crm;

use App\Models\Crm\CoverageCheck;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CoverageCheckService
{
    public function __construct(
        private AuditLogger $audit,
        private LeadPipelineService $pipeline,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Lead $lead, array $data, ?User $actor = null): CoverageCheck
    {
        $result = $data['result'] ?? null;
        if (! in_array($result, CoverageCheck::RESULTS, true)) {
            throw new InvalidArgumentException("Invalid coverage result [{$result}].");
        }

        return DB::transaction(function () use ($lead, $data, $actor, $result) {
            $check = CoverageCheck::query()->create([
                'lead_id' => $lead->id,
                'result' => $result,
                'candidate_tower' => $data['candidate_tower'] ?? null,
                'distance_m' => $data['distance_m'] ?? null,
                'notes' => $data['notes'] ?? null,
                'engineer_id' => $data['engineer_id'] ?? $actor?->id,
                'signal_estimate' => $data['signal_estimate'] ?? null,
                'checked_at' => $data['checked_at'] ?? now(),
            ]);

            $this->audit->log('crm.coverage.recorded', $check, null, $check->toArray(), $lead->branch_id);

            if ($lead->pipeline_stage === Lead::STAGE_QUALIFIED || $lead->pipeline_stage === Lead::STAGE_CONTACTED) {
                if ($lead->canTransitionTo(Lead::STAGE_COVERAGE_CHECK)) {
                    $this->pipeline->transition($lead, Lead::STAGE_COVERAGE_CHECK, $actor, 'coverage_check');
                }
            } elseif ($lead->pipeline_stage === Lead::STAGE_LEAD && $lead->canTransitionTo(Lead::STAGE_CONTACTED)) {
                // no auto jump from lead
            } elseif ($lead->canTransitionTo(Lead::STAGE_COVERAGE_CHECK)) {
                $this->pipeline->transition($lead, Lead::STAGE_COVERAGE_CHECK, $actor, 'coverage_check');
            }

            return $check->fresh();
        });
    }
}
