<?php

namespace App\Services\Crm;

use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpportunityService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromLead(Lead $lead, array $data = [], ?User $actor = null): Opportunity
    {
        return DB::transaction(function () use ($lead, $data) {
            $opportunity = Opportunity::query()->create([
                'lead_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'title' => $data['title'] ?? ($lead->company ?: $lead->contact_person ?: $lead->lead_number),
                'stage' => $data['stage'] ?? $lead->pipeline_stage,
                'probability' => $data['probability'] ?? $lead->probability,
                'value' => $data['value'] ?? $lead->opportunity_value ?? $lead->lead_value,
                'expected_close_date' => $data['expected_close_date'] ?? $lead->expected_close_date,
                'salesperson_id' => $data['salesperson_id'] ?? $lead->salesperson_id,
            ]);

            $this->audit->log('crm.opportunity.created', $opportunity, null, $opportunity->toArray(), $lead->branch_id);

            return $opportunity->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Opportunity $opportunity, array $data, ?User $actor = null): Opportunity
    {
        $fields = [
            'title', 'stage', 'probability', 'value', 'expected_close_date',
            'salesperson_id', 'lost_reason', 'win_reason',
        ];
        $old = $opportunity->only($fields);
        $opportunity->fill(collect($data)->only($fields)->all());
        $opportunity->save();
        $this->audit->log('crm.opportunity.updated', $opportunity, $old, $opportunity->only($fields), $opportunity->branch_id);

        return $opportunity->fresh();
    }

    public function syncFromLead(Lead $lead): ?Opportunity
    {
        $opportunity = $lead->opportunities()->orderByDesc('id')->first();
        if (! $opportunity) {
            return null;
        }

        $opportunity->stage = $lead->pipeline_stage;
        $opportunity->probability = $lead->probability;
        $opportunity->value = $lead->opportunity_value ?? $lead->lead_value;
        $opportunity->expected_close_date = $lead->expected_close_date;
        $opportunity->salesperson_id = $lead->salesperson_id;
        $opportunity->lost_reason = $lead->lost_reason;
        $opportunity->win_reason = $lead->win_reason;
        $opportunity->save();

        return $opportunity;
    }
}
