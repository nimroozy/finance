<?php

namespace App\Services\Crm;

use App\Events\Crm\QuotationAccepted;
use App\Models\Crm\Lead;
use App\Models\Crm\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationService
{
    public function __construct(
        private QuoteNumberService $numbers,
        private LeadPipelineService $pipeline,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Lead $lead, array $data, ?User $actor = null): Quotation
    {
        return DB::transaction(function () use ($lead, $data) {
            $quote = Quotation::query()->create([
                'quote_number' => $this->numbers->nextNumber((int) $lead->branch_id),
                'lead_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'monthly_package' => $data['monthly_package'] ?? null,
                'installation_fee' => $data['installation_fee'] ?? null,
                'discount' => $data['discount'] ?? null,
                'equipment_json' => $data['equipment_json'] ?? null,
                'taxes' => $data['taxes'] ?? null,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'status' => Quotation::STATUS_DRAFT,
            ]);

            $this->audit->log('crm.quotation.created', $quote, null, $quote->toArray(), $lead->branch_id);

            if ($lead->canTransitionTo(Lead::STAGE_QUOTATION)) {
                $this->pipeline->transition($lead, Lead::STAGE_QUOTATION, null, 'quotation_created');
            }

            return $quote->fresh();
        });
    }

    public function send(Quotation $quote, ?User $actor = null): Quotation
    {
        if (! in_array($quote->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT], true)) {
            throw new InvalidArgumentException('Only draft quotations can be sent.');
        }

        $old = ['status' => $quote->status];
        $quote->status = Quotation::STATUS_SENT;
        $quote->save();
        $this->audit->log('crm.quotation.sent', $quote, $old, ['status' => $quote->status], $quote->branch_id);

        return $quote->fresh();
    }

    public function accept(Quotation $quote, ?User $actor = null): Quotation
    {
        if (! in_array($quote->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT], true)) {
            throw new InvalidArgumentException('Quotation cannot be accepted in current status.');
        }

        return DB::transaction(function () use ($quote, $actor) {
            $old = ['status' => $quote->status];
            $quote->status = Quotation::STATUS_ACCEPTED;
            $quote->approved_by = $actor?->id;
            $quote->save();

            $lead = $quote->lead;
            if ($lead) {
                if ($quote->monthly_package !== null) {
                    $lead->estimated_mrr = $quote->monthly_package;
                }
                if ($quote->installation_fee !== null) {
                    $lead->expected_installation_fee = $quote->installation_fee;
                }
                $lead->opportunity_value = (float) ($quote->monthly_package ?? 0) + (float) ($quote->installation_fee ?? 0);
                $lead->save();

                if ($lead->canTransitionTo(Lead::STAGE_APPROVED)) {
                    $this->pipeline->transition($lead, Lead::STAGE_APPROVED, $actor, 'quotation_accepted');
                } elseif ($lead->canTransitionTo(Lead::STAGE_NEGOTIATION)) {
                    $this->pipeline->transition($lead, Lead::STAGE_NEGOTIATION, $actor, 'quotation_accepted');
                }
            }

            $this->audit->log('crm.quotation.accepted', $quote, $old, [
                'status' => $quote->status,
                'approved_by' => $quote->approved_by,
            ], $quote->branch_id);

            QuotationAccepted::dispatch($quote->id, (int) $quote->branch_id, (int) $quote->lead_id);

            return $quote->fresh();
        });
    }

    public function reject(Quotation $quote, ?User $actor = null, ?string $reason = null): Quotation
    {
        if (! in_array($quote->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_SENT], true)) {
            throw new InvalidArgumentException('Quotation cannot be rejected in current status.');
        }

        $old = ['status' => $quote->status];
        $quote->status = Quotation::STATUS_REJECTED;
        if ($reason) {
            $quote->notes = trim(($quote->notes ? $quote->notes."\n" : '').'Rejected: '.$reason);
        }
        $quote->save();
        $this->audit->log('crm.quotation.rejected', $quote, $old, ['status' => $quote->status], $quote->branch_id, $reason);

        return $quote->fresh();
    }
}
