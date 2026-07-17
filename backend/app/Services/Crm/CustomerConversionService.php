<?php

namespace App\Services\Crm;

use App\Events\Crm\InstallationRequestedFromCrm;
use App\Events\Crm\LeadConverted;
use App\Models\Collector;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Installations\InstallationQueueService;
use App\Services\Ownership\CustomerOwnershipService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerConversionService
{
    public function __construct(
        private InstallationQueueService $installations,
        private LeadPipelineService $pipeline,
        private OpportunityService $opportunities,
        private AuditLogger $audit,
        private ?CustomerOwnershipService $ownership = null,
    ) {
        $this->ownership ??= app(CustomerOwnershipService::class);
    }

    /**
     * Convert a won lead into an Installation request against an existing Zoho-linked customer.
     *
     * Requires a local customer with zoho_contact_id (synced/mirrored from Zoho).
     * Does not create Zoho contacts, does not dispatch Radius activation, and does not
     * invent local-only customers — use CrmZohoCustomerLinkService to search/link first.
     *
     * @param  array<string, mixed>  $options
     * @return array{lead: Lead, customer: Customer, installation_id: int}
     */
    public function convert(Lead $lead, array $options = [], ?User $actor = null): array
    {
        if ($lead->converted_customer_id && $lead->installation_id) {
            throw new InvalidArgumentException('Lead already converted.');
        }

        return DB::transaction(function () use ($lead, $options, $actor) {
            $customer = $this->resolveCustomer($lead, $options);

            if (! $customer->zoho_contact_id) {
                throw new InvalidArgumentException(
                    'Conversion requires an existing Zoho-linked customer (zoho_contact_id). Search the Zoho mirror and link the lead before converting.'
                );
            }

            $installation = $this->installations->create([
                'branch_id' => $lead->branch_id,
                'customer_id' => $customer->id,
                'prospect_name' => $lead->company ?: $lead->contact_person,
                'contact_name' => $lead->contact_person,
                'phone' => $lead->phone,
                'address' => $lead->address,
                'gps_lat' => $lead->gps_lat,
                'gps_lng' => $lead->gps_lng,
                'requested_package' => $lead->interested_package,
                'salesperson_id' => $lead->salesperson_id,
                'installation_fee' => $lead->expected_installation_fee,
                'monthly_fee_estimate' => $lead->estimated_mrr,
                'notes' => 'Created from CRM lead '.$lead->lead_number,
                'expand_template' => (bool) ($options['expand_template'] ?? false),
                'template_code' => $options['template_code'] ?? null,
            ], $actor);

            $lead->converted_customer_id = $customer->id;
            $lead->installation_id = $installation->id;
            $lead->win_reason = $options['win_reason'] ?? $lead->win_reason ?? 'converted';
            $lead->save();

            $lead = $this->advanceToInstallationRequest($lead, $actor);

            if (! empty($options['collector_id']) && $actor) {
                $collector = Collector::query()->find($options['collector_id']);
                if ($collector) {
                    try {
                        $this->ownership->assignPermanent(
                            $customer,
                            $collector,
                            $actor,
                            now()->toDateString(),
                            'CRM conversion ownership',
                        );
                    } catch (InvalidArgumentException) {
                        // Ownership already set or ineligible — do not fail conversion.
                    }
                }
            }

            $this->opportunities->syncFromLead($lead->fresh());

            $this->audit->log('crm.lead.converted', $lead, null, [
                'customer_id' => $customer->id,
                'installation_id' => $installation->id,
                'zoho_contact_id' => $customer->zoho_contact_id,
            ], $lead->branch_id);

            LeadConverted::dispatch($lead->id, (int) $lead->branch_id, (int) $customer->id, (int) $installation->id);
            InstallationRequestedFromCrm::dispatch($lead->id, (int) $installation->id, (int) $lead->branch_id);

            return [
                'lead' => $lead->fresh(['convertedCustomer', 'installation']),
                'customer' => $customer,
                'installation_id' => (int) $installation->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveCustomer(Lead $lead, array $options): Customer
    {
        if (! empty($options['customer_id'])) {
            return Customer::query()->findOrFail($options['customer_id']);
        }

        if ($lead->converted_customer_id) {
            return Customer::query()->findOrFail($lead->converted_customer_id);
        }

        throw new InvalidArgumentException(
            'No Zoho-linked customer on this lead. POST /crm/leads/{id}/link-zoho-customer or pass customer_id after searching GET /crm/customers/search-zoho-mirror.'
        );
    }

    private function advanceToInstallationRequest(Lead $lead, ?User $actor): Lead
    {
        if ($lead->pipeline_stage === Lead::STAGE_INSTALLATION_REQUEST) {
            return $lead;
        }

        $path = [
            Lead::STAGE_CONTACTED,
            Lead::STAGE_QUALIFIED,
            Lead::STAGE_COVERAGE_CHECK,
            Lead::STAGE_SITE_SURVEY,
            Lead::STAGE_QUOTATION,
            Lead::STAGE_NEGOTIATION,
            Lead::STAGE_APPROVED,
            Lead::STAGE_FINANCE_REVIEW,
            Lead::STAGE_INSTALLATION_REQUEST,
        ];

        foreach ($path as $stage) {
            $lead = $lead->fresh();
            if ($lead->pipeline_stage === Lead::STAGE_INSTALLATION_REQUEST) {
                return $lead;
            }
            if ($lead->canTransitionTo($stage)) {
                $lead = $this->pipeline->transition($lead, $stage, $actor, 'converted');
            }
        }

        $lead = $lead->fresh();
        if ($lead->pipeline_stage !== Lead::STAGE_INSTALLATION_REQUEST) {
            $from = $lead->pipeline_stage;
            $lead->pipeline_stage = Lead::STAGE_INSTALLATION_REQUEST;
            $lead->save();
            \App\Models\Crm\LeadStageTransition::query()->create([
                'lead_id' => $lead->id,
                'from_stage' => $from,
                'to_stage' => Lead::STAGE_INSTALLATION_REQUEST,
                'user_id' => $actor?->id,
                'reason' => 'converted',
                'notes' => 'Forced stage on CRM conversion',
                'created_at' => now(),
            ]);
            $this->audit->log('crm.lead.stage_changed', $lead, ['pipeline_stage' => $from], [
                'pipeline_stage' => Lead::STAGE_INSTALLATION_REQUEST,
            ], $lead->branch_id, 'converted');
        }

        return $lead->fresh();
    }
}
