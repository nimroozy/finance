<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServicePackageChanged;
use App\Models\Services\Service;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServicePackage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceChangeRequestService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function request(Service $service, array $data, ?User $actor = null): ServiceChangeRequest
    {
        if (empty($data['requested_values']) || ! is_array($data['requested_values'])) {
            throw new InvalidArgumentException('Change request requires requested_values.');
        }

        $current = [
            'package_id' => $service->package_id,
            'package_version_id' => $service->package_version_id,
            'mrr' => $service->mrr,
            'service_type_code' => $service->service_type_code,
            'access_technology_code' => $service->access_technology_code,
        ];

        $cr = ServiceChangeRequest::query()->create([
            'service_id' => $service->id,
            'type' => $data['type'] ?? 'package_change',
            'current_values' => $current,
            'requested_values' => $data['requested_values'],
            'requested_by' => $actor?->id,
            'effective_at' => $data['effective_at'] ?? null,
            'reason' => $data['reason'] ?? null,
            'finance_status' => 'pending',
            'technical_status' => 'pending',
            'status' => ServiceChangeRequest::STATUS_REQUESTED,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log('service.change_requested', $service, $current, $data['requested_values'], $service->branch_id);

        return $cr;
    }

    public function submitTechnicalReview(ServiceChangeRequest $cr, ?User $actor = null, bool $approved = true, ?string $notes = null): ServiceChangeRequest
    {
        if (! in_array($cr->status, [ServiceChangeRequest::STATUS_REQUESTED, ServiceChangeRequest::STATUS_PENDING], true)) {
            throw new InvalidArgumentException('Change request is not awaiting technical review.');
        }

        $cr->technical_status = $approved ? 'approved' : 'rejected';
        if (! $approved) {
            $cr->status = ServiceChangeRequest::STATUS_REJECTED;
        } else {
            $cr->status = ServiceChangeRequest::STATUS_TECHNICAL_REVIEW;
            // Advance into finance review once technical passes.
            $cr->status = ServiceChangeRequest::STATUS_FINANCE_REVIEW;
        }
        if ($notes) {
            $cr->notes = trim(($cr->notes ? $cr->notes."\n" : '').$notes);
        }
        $cr->save();

        $this->audit->log('service.change_technical_review', $cr->service, null, [
            'change_request_id' => $cr->id,
            'approved' => $approved,
        ], $cr->service?->branch_id);

        return $cr->fresh();
    }

    public function submitFinanceReview(ServiceChangeRequest $cr, ?User $actor = null, bool $approved = true, ?string $notes = null): ServiceChangeRequest
    {
        if ($cr->status !== ServiceChangeRequest::STATUS_FINANCE_REVIEW) {
            throw new InvalidArgumentException('Change request is not awaiting finance review.');
        }

        $cr->finance_status = $approved ? 'approved' : 'rejected';
        if (! $approved) {
            $cr->status = ServiceChangeRequest::STATUS_REJECTED;
        } else {
            $cr->status = ServiceChangeRequest::STATUS_APPROVED;
        }
        if ($notes) {
            $cr->notes = trim(($cr->notes ? $cr->notes."\n" : '').$notes);
        }
        $cr->save();

        $this->audit->log('service.change_finance_review', $cr->service, null, [
            'change_request_id' => $cr->id,
            'approved' => $approved,
        ], $cr->service?->branch_id);

        return $cr->fresh();
    }

    /**
     * Mark approved after reviews. Does NOT apply unless $apply is explicitly true.
     */
    public function approve(ServiceChangeRequest $cr, ?User $actor = null, bool $apply = false): ServiceChangeRequest
    {
        if (in_array($cr->status, [ServiceChangeRequest::STATUS_REQUESTED, ServiceChangeRequest::STATUS_PENDING], true)) {
            $cr = $this->submitTechnicalReview($cr, $actor, true);
        }
        if ($cr->status === ServiceChangeRequest::STATUS_FINANCE_REVIEW) {
            $cr = $this->submitFinanceReview($cr, $actor, true);
        }

        if ($cr->status !== ServiceChangeRequest::STATUS_APPROVED && $cr->status !== ServiceChangeRequest::STATUS_APPLIED) {
            if ($cr->technical_status === 'approved' && $cr->finance_status === 'approved') {
                $cr->status = ServiceChangeRequest::STATUS_APPROVED;
                $cr->save();
            } else {
                throw new InvalidArgumentException('Change request cannot be approved without technical and finance reviews.');
            }
        }

        if ($apply) {
            return $this->apply($cr, $actor);
        }

        return $cr->fresh();
    }

    public function apply(ServiceChangeRequest $cr, ?User $actor = null): ServiceChangeRequest
    {
        if (! in_array($cr->status, [ServiceChangeRequest::STATUS_APPROVED], true)) {
            throw new InvalidArgumentException('Only approved change requests can be applied.');
        }

        return DB::transaction(function () use ($cr, $actor) {
            $service = Service::query()->lockForUpdate()->findOrFail($cr->service_id);
            $values = $cr->requested_values ?? [];

            if (! empty($values['package_id'])) {
                $package = ServicePackage::query()->findOrFail($values['package_id']);
                $version = ! empty($values['package_version_id'])
                    ? $package->versions()->whereKey($values['package_version_id'])->firstOrFail()
                    : $package->latestVersion();

                $service->package_id = $package->id;
                $service->package_version_id = $version?->id;
                $service->service_type_code = $package->service_type_code;
                $service->access_technology_code = $package->access_technology_code;
                $service->mrr = $version?->monthly_price ?? $package->monthly_price;
                $service->billing_frequency = $package->billing_frequency;
                $service->sla_template_id = $package->sla_template_id;
            }

            $service->updated_by = $actor?->id;
            $service->save();

            $cr->status = ServiceChangeRequest::STATUS_APPLIED;
            $cr->save();

            $this->audit->log('service.package_changed', $service, $cr->current_values, $cr->requested_values, $service->branch_id);
            ServicePackageChanged::dispatch($service->id, (int) $service->branch_id);

            return $cr->fresh();
        });
    }

    public function close(ServiceChangeRequest $cr, ?User $actor = null): ServiceChangeRequest
    {
        if (! in_array($cr->status, [ServiceChangeRequest::STATUS_APPLIED, ServiceChangeRequest::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException('Only applied or rejected change requests can be closed.');
        }

        $cr->status = ServiceChangeRequest::STATUS_CLOSED;
        $cr->save();

        $this->audit->log('service.change_closed', $cr->service, null, [
            'change_request_id' => $cr->id,
        ], $cr->service?->branch_id);

        return $cr->fresh();
    }
}
