<?php

namespace App\Services\Crm;

use App\Events\Crm\LeadCreated;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeadService
{
    public function __construct(
        private LeadNumberService $numbers,
        private DuplicateLeadDetector $duplicates,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{lead: Lead, duplicate_warning: list<array{id: int, lead_number: string, phone: ?string}>}
     */
    public function create(array $data, ?User $actor = null): array
    {
        if (empty($data['branch_id'])) {
            throw new InvalidArgumentException('Lead requires branch_id.');
        }

        $branchId = (int) $data['branch_id'];
        $stage = $data['pipeline_stage'] ?? $data['status'] ?? Lead::STAGE_LEAD;
        if (! in_array($stage, Lead::PIPELINE_STAGES, true)) {
            throw new InvalidArgumentException("Invalid pipeline stage [{$stage}].");
        }

        if (! empty($data['customer_type']) && ! in_array($data['customer_type'], Lead::CUSTOMER_TYPES, true)) {
            throw new InvalidArgumentException('Invalid customer_type.');
        }

        $duplicateWarning = [];
        if (! empty($data['phone'])) {
            $duplicateWarning = $this->duplicates->findByPhone((string) $data['phone'], $branchId)
                ->map(fn (Lead $l) => [
                    'id' => $l->id,
                    'lead_number' => $l->lead_number,
                    'phone' => $l->phone,
                ])
                ->values()
                ->all();
        }

        $lead = DB::transaction(function () use ($data, $actor, $branchId, $stage) {
            $lead = Lead::query()->create([
                'lead_number' => $this->numbers->nextNumber($branchId),
                'branch_id' => $branchId,
                'organization_id' => $data['organization_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company' => $data['company'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'source_code' => $data['source_code'] ?? null,
                'campaign' => $data['campaign'] ?? null,
                'referral' => $data['referral'] ?? null,
                'salesperson_id' => $data['salesperson_id'] ?? $actor?->id,
                'lead_value' => $data['lead_value'] ?? null,
                'estimated_mrr' => $data['estimated_mrr'] ?? null,
                'expected_installation_fee' => $data['expected_installation_fee'] ?? null,
                'interested_package' => $data['interested_package'] ?? null,
                'customer_type' => $data['customer_type'] ?? null,
                'pipeline_stage' => $stage,
                'owner_id' => $data['owner_id'] ?? $data['salesperson_id'] ?? $actor?->id,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'probability' => $data['probability'] ?? null,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'competitor' => $data['competitor'] ?? null,
                'opportunity_value' => $data['opportunity_value'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $this->audit->log('crm.lead.created', $lead, null, $lead->toArray(), $branchId);

            return $lead;
        });

        LeadCreated::dispatch($lead->id, $branchId);

        return [
            'lead' => $lead->fresh(['source', 'salesperson', 'owner']),
            'duplicate_warning' => $duplicateWarning,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data, ?User $actor = null): Lead
    {
        if ($lead->isTerminal() && empty($data['force'])) {
            throw new InvalidArgumentException('Terminal leads cannot be updated.');
        }

        return DB::transaction(function () use ($lead, $data) {
            $fields = [
                'organization_id', 'contact_id', 'company', 'contact_person', 'phone', 'whatsapp',
                'email', 'address', 'gps_lat', 'gps_lng', 'source_code', 'campaign', 'referral',
                'salesperson_id', 'lead_value', 'estimated_mrr', 'expected_installation_fee',
                'interested_package', 'customer_type', 'owner_id', 'next_follow_up_at',
                'probability', 'expected_close_date', 'competitor', 'opportunity_value', 'notes',
            ];

            $old = $lead->only($fields);
            $lead->fill(collect($data)->only($fields)->all());
            $lead->save();

            $this->audit->log('crm.lead.updated', $lead, $old, $lead->only($fields), $lead->branch_id);

            return $lead->fresh();
        });
    }

    public function assign(Lead $lead, int $ownerId, ?User $actor = null, ?int $salespersonId = null): Lead
    {
        return DB::transaction(function () use ($lead, $ownerId, $salespersonId) {
            $old = $lead->only(['owner_id', 'salesperson_id']);
            $lead->owner_id = $ownerId;
            if ($salespersonId !== null) {
                $lead->salesperson_id = $salespersonId;
            } else {
                $lead->salesperson_id = $ownerId;
            }
            $lead->save();

            $this->audit->log('crm.lead.assigned', $lead, $old, $lead->only(['owner_id', 'salesperson_id']), $lead->branch_id);

            return $lead->fresh();
        });
    }
}
