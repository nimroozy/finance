<?php

namespace App\Services\Mapping;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerBranchMappingConflict;
use App\Models\User;

class UnmappedCustomerClassifier
{
    public const CLASS_MISSING_PREFIX = 'missing_prefix';

    public const CLASS_UNKNOWN_PREFIX = 'unknown_prefix';

    public const CLASS_MISSING_CUSTOMER_NUMBER = 'missing_customer_number';

    public const CLASS_MALFORMED_CONTACT_NAME = 'malformed_contact_name';

    public const CLASS_HEADQUARTER = 'Headquarter';

    public const CLASS_TEST = 'test_customer';

    public const CLASS_ARCHIVED = 'archived_customer';

    public const CLASS_LOCAL_ONLY = 'local_only_customer';

    public const CLASS_MULTIPLE_BRANCH = 'multiple_branch_evidence';

    public const CLASS_PROTECTED = 'protected_history';

    public const CLASS_UNRESOLVED = 'unresolved';

    public function __construct(
        private CustomerNumberExtractionService $extractor,
        private CustomerBranchResolutionService $resolver,
    ) {}

    public function classify(Customer $customer): string
    {
        if ($customer->is_headquarter_owned || ($customer->branch && $customer->branch->is_headquarter)) {
            return self::CLASS_HEADQUARTER;
        }
        if ($customer->is_test_customer || $this->looksLikeTest($customer)) {
            return self::CLASS_TEST;
        }
        if ($customer->status === Customer::STATUS_ARCHIVED || $customer->is_non_operational) {
            return self::CLASS_ARCHIVED;
        }
        if ($this->resolver->hasProtectedHistory($customer)) {
            return self::CLASS_PROTECTED;
        }

        $openConflict = CustomerBranchMappingConflict::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerBranchMappingConflict::STATUS_OPEN)
            ->first();
        if ($openConflict?->conflict_type === CustomerBranchMappingConflict::TYPE_MULTIPLE_INVOICE_LOCATIONS) {
            return self::CLASS_MULTIPLE_BRANCH;
        }

        $extraction = $this->extractor->extract($customer);
        return match ($extraction['result_class']) {
            'unknown_prefix' => self::CLASS_UNKNOWN_PREFIX,
            'no_number' => self::CLASS_MISSING_CUSTOMER_NUMBER,
            'malformed_contact_name', 'invalid_format' => self::CLASS_MALFORMED_CONTACT_NAME,
            'conflict' => self::CLASS_MULTIPLE_BRANCH,
            'extracted' => self::CLASS_MISSING_PREFIX, // extractable but still unmapped
            default => $customer->zoho_contact_id ? self::CLASS_UNRESOLVED : self::CLASS_LOCAL_ONLY,
        };
    }

    public function apply(Customer $customer, ?User $actor = null, ?string $notes = null): Customer
    {
        $class = $this->classify($customer->loadMissing('branch'));
        $customer->forceFill([
            'unmapped_classification' => $class,
            'is_test_customer' => $class === self::CLASS_TEST ? true : $customer->is_test_customer,
            'is_headquarter_owned' => $class === self::CLASS_HEADQUARTER ? true : $customer->is_headquarter_owned,
            'exclude_from_field_collection' => in_array($class, [self::CLASS_HEADQUARTER, self::CLASS_TEST, self::CLASS_ARCHIVED], true)
                ? true
                : $customer->exclude_from_field_collection,
            'classification_notes' => $notes ?? $customer->classification_notes,
            'classified_by' => $actor?->id ?? $customer->classified_by,
            'classified_at' => now(),
        ])->save();

        return $customer->fresh();
    }

    protected function looksLikeTest(Customer $customer): bool
    {
        $blob = strtoupper(($customer->contact_name ?? '').' '.($customer->customer_number ?? '').' '.($customer->company_name ?? ''));

        return str_contains($blob, 'TEST') || str_contains($blob, 'STAGE3') || str_contains($blob, 'DUMMY');
    }
}
