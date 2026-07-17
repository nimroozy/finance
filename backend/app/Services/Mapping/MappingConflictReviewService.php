<?php

namespace App\Services\Mapping;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerBranchMappingConflict;
use App\Models\CustomerCollectorOwnership;
use App\Models\CustomerNumberBackfillHistory;
use App\Models\TemporaryCollectionAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MappingConflictReviewService
{
    public const ACTION_KEEP_CURRENT = 'keep_current_branch';

    public const ACTION_APPLY_PREFIX = 'apply_prefix_branch';

    public const ACTION_APPLY_LOCATION = 'apply_invoice_location_branch';

    public const ACTION_SELECT_BRANCH = 'select_branch_manually';

    public const ACTION_ADMIN_OVERRIDE = 'add_administrator_override';

    public const ACTION_MARK_HQ = 'mark_headquarter';

    public const ACTION_MARK_NON_OPERATIONAL = 'mark_non_operational';

    public const ACTION_IGNORE_TEMP = 'ignore_temporarily';

    public const ACTION_CORRECT_NUMBER = 'correct_customer_number';

    public const ACTION_ADD_PREFIX = 'add_new_prefix';

    public function __construct(
        private CustomerBranchResolutionService $resolver,
        private CustomerNumberExtractionService $extractor,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reviewPayload(CustomerBranchMappingConflict $conflict): array
    {
        $customer = Customer::withoutGlobalScopes()->with(['branch'])->findOrFail($conflict->customer_id);
        $extraction = $this->extractor->extract($customer);
        $resolution = $this->resolver->resolve($customer);
        $protected = $this->resolver->hasProtectedHistory($customer);

        $ownership = CustomerCollectorOwnership::query()
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->with(['collector.user:id,name'])
            ->first();
        $temp = TemporaryCollectionAssignment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        $risk = $protected ? 'high' : (($conflict->conflict_type === CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH) ? 'medium' : 'low');
        $recommended = $this->recommend($conflict, $resolution, $protected);

        return [
            'conflict' => $conflict->load(['prefixBranch:id,code,name_en', 'locationBranch:id,code,name_en']),
            'customer' => [
                'id' => $customer->id,
                'contact_name' => $customer->contact_name,
                'customer_number' => $customer->customer_number,
                'extracted_customer_number' => $extraction['extracted_customer_number'],
                'clean_display_name' => $extraction['clean_display_name'],
                'branch_id' => $customer->branch_id,
                'branch' => $customer->branch?->only(['id', 'code', 'name_en', 'name_fa']),
                'is_unmapped' => $customer->is_unmapped,
                'administrator_branch_override' => $customer->administrator_branch_override,
                'unmapped_classification' => $customer->unmapped_classification,
                'is_headquarter_owned' => $customer->is_headquarter_owned,
            ],
            'detected_prefix' => $conflict->detected_prefix ?: ($extraction['prefix'] ?? $resolution['prefix_detected']),
            'prefix_branch_id' => $conflict->prefix_branch_id ?: ($extraction['branch_id'] ?? null),
            'invoice_location_evidence' => $resolution['invoice_location_evidence'],
            'reporting_tag_evidence' => $resolution['reporting_tag_evidence'],
            'existing_override' => (bool) $customer->administrator_branch_override,
            'permanent_collector' => $ownership?->collector?->user?->only(['id', 'name']),
            'temporary_assignment' => $temp?->only(['id', 'status', 'starts_at', 'ends_at', 'collector_id']),
            'protected_history' => $protected,
            'counts' => [
                'payments' => \App\Models\Payment::withoutGlobalScopes()->where('customer_id', $customer->id)->count(),
                'receipts' => \App\Models\Receipt::withoutGlobalScopes()->whereHas('payment', fn ($q) => $q->withoutGlobalScopes()->where('customer_id', $customer->id))->count(),
                'handovers' => \App\Models\Payment::withoutGlobalScopes()->where('customer_id', $customer->id)->whereIn('handover_status', ['handed_over', 'partially_handed_over'])->count(),
                'routes' => \App\Models\CustomerAssignment::withoutGlobalScopes()->where('customer_id', $customer->id)->where('is_active', true)->count(),
                'promises' => \App\Models\PromiseToPay::withoutGlobalScopes()->where('customer_id', $customer->id)->count(),
            ],
            'recommended_action' => $recommended,
            'risk_level' => $risk,
            'resolution' => $resolution,
            'extraction' => $extraction,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(CustomerBranchMappingConflict $conflict, User $actor, array $data): CustomerBranchMappingConflict
    {
        $action = (string) ($data['action'] ?? '');
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for every resolution.']);
        }

        $customer = Customer::withoutGlobalScopes()->findOrFail($conflict->customer_id);

        return DB::transaction(function () use ($conflict, $actor, $data, $action, $reason, $customer) {
            match ($action) {
                self::ACTION_KEEP_CURRENT => $this->keepCurrent($customer, $actor, $reason),
                self::ACTION_APPLY_PREFIX => $this->applyBranch($customer, $conflict->prefix_branch_id ?: ($data['branch_id'] ?? null), $actor, $reason, adminOverride: false),
                self::ACTION_APPLY_LOCATION => $this->applyBranch($customer, $conflict->location_branch_id ?: ($data['branch_id'] ?? null), $actor, $reason, adminOverride: false),
                self::ACTION_SELECT_BRANCH, self::ACTION_ADMIN_OVERRIDE => $this->applyBranch($customer, $data['branch_id'] ?? null, $actor, $reason, adminOverride: true),
                self::ACTION_MARK_HQ => $this->markHeadquarter($customer, $actor, $reason),
                self::ACTION_MARK_NON_OPERATIONAL => $this->markNonOperational($customer, $actor, $reason),
                self::ACTION_IGNORE_TEMP => null,
                self::ACTION_CORRECT_NUMBER => $this->correctNumber($customer, $actor, $data, $reason),
                self::ACTION_ADD_PREFIX => null, // prefix created via separate endpoint; still close conflict if branch chosen
                default => throw ValidationException::withMessages(['action' => 'Unsupported resolution action.']),
            };

            if ($action === self::ACTION_ADD_PREFIX && ! empty($data['branch_id'])) {
                $this->applyBranch($customer, $data['branch_id'], $actor, $reason, adminOverride: true);
            }

            $deferred = $action === self::ACTION_IGNORE_TEMP;
            $conflict->update([
                'status' => $deferred ? CustomerBranchMappingConflict::STATUS_OPEN : CustomerBranchMappingConflict::STATUS_RESOLVED,
                'deferred' => $deferred,
                'deferred_until' => $deferred ? now()->addDays((int) ($data['defer_days'] ?? 7)) : null,
                'resolution_action' => $action,
                'resolution_notes' => $reason,
                'resolved_by' => $actor->id,
                'resolved_at' => $deferred ? null : now(),
                'resolved_branch_id' => $customer->fresh()->branch_id,
                'risk_level' => $data['risk_level'] ?? $conflict->risk_level,
                'recommended_action' => $data['recommended_action'] ?? $conflict->recommended_action,
            ]);

            $this->audit->log('mapping.conflict_resolved', $conflict, null, [
                'action' => $action,
                'reason' => $reason,
                'customer_id' => $customer->id,
            ], $customer->branch_id);

            return $conflict->fresh();
        });
    }

    protected function recommend(CustomerBranchMappingConflict $conflict, array $resolution, bool $protected): string
    {
        if ($protected) {
            return self::ACTION_KEEP_CURRENT;
        }

        return match ($conflict->conflict_type) {
            CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH => self::ACTION_ADMIN_OVERRIDE,
            CustomerBranchMappingConflict::TYPE_UNKNOWN_PREFIX => self::ACTION_ADD_PREFIX,
            default => self::ACTION_APPLY_PREFIX,
        };
    }

    protected function keepCurrent(Customer $customer, User $actor, string $reason): void
    {
        if ($customer->branch_id) {
            $customer->forceFill([
                'is_unmapped' => false,
                'administrator_branch_override' => true,
                'branch_override_by' => $actor->id,
                'branch_mapping_source' => CustomerBranchResolutionService::SOURCE_ADMIN_OVERRIDE,
                'classification_notes' => $reason,
                'classified_by' => $actor->id,
                'classified_at' => now(),
            ])->save();
        }
    }

    protected function applyBranch(Customer $customer, mixed $branchId, User $actor, string $reason, bool $adminOverride): void
    {
        $branchId = (int) $branchId;
        if ($branchId <= 0 || ! Branch::withoutGlobalScopes()->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'A valid branch is required.']);
        }

        if ($adminOverride) {
            $this->resolver->setAdministratorOverride($customer, $branchId, $actor, $reason);
        } else {
            $customer->forceFill([
                'branch_id' => $branchId,
                'is_unmapped' => false,
                'status' => $customer->status === Customer::STATUS_UNMAPPED ? Customer::STATUS_ACTIVE : $customer->status,
                'branch_mapping_source' => CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX,
                'branch_mapped_at' => now(),
                'classification_notes' => $reason,
                'classified_by' => $actor->id,
                'classified_at' => now(),
            ])->save();
        }
    }

    protected function markHeadquarter(Customer $customer, User $actor, string $reason): void
    {
        $hq = Branch::withoutGlobalScopes()->where('is_headquarter', true)->first();
        $customer->forceFill([
            'branch_id' => $hq?->id ?? $customer->branch_id,
            'is_unmapped' => false,
            'is_headquarter_owned' => true,
            'exclude_from_field_collection' => true,
            'unmapped_classification' => UnmappedCustomerClassifier::CLASS_HEADQUARTER,
            'classification_notes' => $reason,
            'classified_by' => $actor->id,
            'classified_at' => now(),
            'branch_mapping_source' => CustomerBranchResolutionService::SOURCE_ADMIN_OVERRIDE,
            'administrator_branch_override' => true,
            'branch_override_by' => $actor->id,
        ])->save();
    }

    protected function markNonOperational(Customer $customer, User $actor, string $reason): void
    {
        $customer->forceFill([
            'is_non_operational' => true,
            'exclude_from_field_collection' => true,
            'unmapped_classification' => UnmappedCustomerClassifier::CLASS_ARCHIVED,
            'status' => Customer::STATUS_ARCHIVED,
            'is_unmapped' => false,
            'classification_notes' => $reason,
            'classified_by' => $actor->id,
            'classified_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function correctNumber(Customer $customer, User $actor, array $data, string $reason): void
    {
        $number = trim((string) ($data['customer_number'] ?? ''));
        if ($number === '') {
            throw ValidationException::withMessages(['customer_number' => 'Customer number is required.']);
        }
        $parsed = $this->extractor->extract([
            'customer_number' => $number,
            'contact_name' => $customer->contact_name,
        ]);
        if (! $parsed['valid']) {
            throw ValidationException::withMessages(['customer_number' => $parsed['explanation']]);
        }

        $before = $customer->customer_number;
        $customer->forceFill([
            'customer_number' => $parsed['extracted_customer_number'],
            'extracted_customer_number' => $parsed['extracted_customer_number'],
            'source_contact_name' => $customer->source_contact_name ?: $customer->contact_name,
            'clean_display_name' => $parsed['clean_display_name'] ?: $customer->clean_display_name,
            'customer_number_source' => CustomerNumberExtractionService::SOURCE_ADMIN,
            'customer_number_confidence' => 'high',
            'customer_number_extracted_at' => now(),
            'classification_notes' => $reason,
            'classified_by' => $actor->id,
            'classified_at' => now(),
        ])->save();

        CustomerNumberBackfillHistory::query()->create([
            'customer_id' => $customer->id,
            'before_customer_number' => $before,
            'after_customer_number' => $parsed['extracted_customer_number'],
            'extracted_customer_number' => $parsed['extracted_customer_number'],
            'clean_display_name' => $parsed['clean_display_name'],
            'source' => CustomerNumberExtractionService::SOURCE_ADMIN,
            'confidence' => 'high',
            'result_class' => 'extracted',
            'dry_run' => false,
            'applied' => true,
            'explanation' => $reason,
            'actor_id' => $actor->id,
            'run_id' => 'conflict-correct-'.$customer->id,
        ]);

        if (! empty($parsed['branch_id']) && empty($data['keep_branch'])) {
            $this->resolver->setAdministratorOverride($customer->fresh(), (int) $parsed['branch_id'], $actor, $reason);
        }
    }
}
