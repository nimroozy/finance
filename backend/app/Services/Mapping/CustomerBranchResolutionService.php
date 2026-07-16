<?php

namespace App\Services\Mapping;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\CustomerBranchMappingConflict;
use App\Models\CustomerBranchMappingHistory;
use App\Models\CustomerCollectorOwnership;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PromiseToPay;
use App\Models\Receipt;
use App\Models\TemporaryCollectionAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ownership\CustomerWorkQueueService;
use App\Services\Zoho\ZohoBranchMappingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerBranchResolutionService
{
    public const SOURCE_ADMIN_OVERRIDE = 'administrator_override';

    public const SOURCE_CUSTOMER_PREFIX = 'customer_prefix';

    public const SOURCE_INVOICE_LOCATION = 'invoice_location';

    public const SOURCE_REPORTING_TAG = 'reporting_tag';

    public const SOURCE_EXISTING_MAPPING = 'existing_mapping';

    public const SOURCE_UNMAPPED = 'unmapped';

    public const SOURCE_CONFLICT = 'conflict';

    public function __construct(
        private CustomerNumberPrefixMatcher $matcher,
        private ZohoBranchMappingService $zohoMapping,
        private AuditLogger $audit,
        private CustomerWorkQueueService $workQueues,
    ) {}

    /**
     * @return array{
     *   resolved_branch_id:?int,
     *   resolution_source:string,
     *   confidence:string,
     *   prefix_detected:?string,
     *   invoice_location_evidence:array,
     *   reporting_tag_evidence:array,
     *   existing_mapping_evidence:array,
     *   conflict_type:?string,
     *   explanation:string,
     *   protected_history:bool,
     *   would_change:bool
     * }
     */
    public function resolve(Customer $customer, ?array $zohoPayload = null): array
    {
        $customerNumber = $customer->customer_number;
        $prefixHit = $this->matcher->detect($customerNumber);
        $prefixBranchId = null;
        $prefixDetected = null;
        $ambiguousPrefix = false;

        if ($prefixHit) {
            $ambiguousPrefix = (bool) ($prefixHit['ambiguous'] ?? false);
            $prefixDetected = $prefixHit['prefix'] ?? null;
            $prefixBranchId = $ambiguousPrefix ? null : (int) ($prefixHit['branch_id'] ?? 0);
            if ($prefixBranchId === 0) {
                $prefixBranchId = null;
            }
        }

        $invoiceEvidence = $this->invoiceLocationEvidence($customer);
        $locationBranchIds = $invoiceEvidence['branch_ids'];
        $locationBranchId = $invoiceEvidence['primary_branch_id'];

        $tagBranchId = $this->zohoMapping->resolveBranchId([
            'reporting_tags' => $customer->reporting_tags ?? ($zohoPayload['reporting_tags'] ?? []),
        ]);
        if ($zohoPayload) {
            $payloadLocationBranch = $this->zohoMapping->resolveBranchId($zohoPayload);
            if ($payloadLocationBranch && ! $locationBranchId) {
                $locationBranchId = $payloadLocationBranch;
            }
        }

        $existingBranchId = $customer->branch_id ? (int) $customer->branch_id : null;
        $protected = $this->hasProtectedHistory($customer);

        $base = [
            'prefix_detected' => $prefixDetected,
            'prefix_branch_id' => $prefixBranchId,
            'invoice_location_evidence' => $invoiceEvidence,
            'reporting_tag_evidence' => ['branch_id' => $tagBranchId],
            'existing_mapping_evidence' => [
                'branch_id' => $existingBranchId,
                'administrator_override' => (bool) $customer->administrator_branch_override,
                'mapping_source' => $customer->branch_mapping_source,
            ],
            'protected_history' => $protected,
            'would_change' => false,
        ];

        // 1. Administrator override always wins.
        if ($customer->administrator_branch_override && $existingBranchId) {
            if ($prefixBranchId && $prefixBranchId !== $existingBranchId) {
                return array_merge($base, [
                    'resolved_branch_id' => $existingBranchId,
                    'resolution_source' => self::SOURCE_ADMIN_OVERRIDE,
                    'confidence' => 'high',
                    'conflict_type' => CustomerBranchMappingConflict::TYPE_ADMINISTRATOR_OVERRIDE_CONFLICT,
                    'explanation' => 'Administrator branch override is active; prefix suggests a different branch. Override wins until removed.',
                    'would_change' => false,
                ]);
            }

            return array_merge($base, [
                'resolved_branch_id' => $existingBranchId,
                'resolution_source' => self::SOURCE_ADMIN_OVERRIDE,
                'confidence' => 'high',
                'conflict_type' => null,
                'explanation' => 'Administrator branch override.',
            ]);
        }

        if ($ambiguousPrefix) {
            return $this->conflictResult(array_merge($base, [
                'resolved_branch_id' => null,
                'resolution_source' => self::SOURCE_CONFLICT,
                'confidence' => 'none',
                'conflict_type' => CustomerBranchMappingConflict::TYPE_DUPLICATE_PREFIX_CONFIGURATION,
                'explanation' => 'Multiple active prefix configurations match this customer number.',
            ]));
        }

        // Multiple invoice locations → conflict
        if (count($locationBranchIds) > 1) {
            return $this->conflictResult(array_merge($base, [
                'resolved_branch_id' => null,
                'resolution_source' => self::SOURCE_CONFLICT,
                'confidence' => 'none',
                'conflict_type' => CustomerBranchMappingConflict::TYPE_MULTIPLE_INVOICE_LOCATIONS,
                'explanation' => 'Customer invoices map to more than one branch location.',
            ]));
        }

        // 2. Customer number prefix
        if ($prefixBranchId) {
            if ($locationBranchId && $locationBranchId !== $prefixBranchId) {
                return $this->conflictResult(array_merge($base, [
                    'resolved_branch_id' => null,
                    'resolution_source' => self::SOURCE_CONFLICT,
                    'confidence' => 'none',
                    'conflict_type' => CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH,
                    'explanation' => 'Customer number prefix branch disagrees with Zoho invoice location branch.',
                ]));
            }

            if ($existingBranchId && $existingBranchId !== $prefixBranchId
                && in_array($customer->branch_mapping_source, [self::SOURCE_EXISTING_MAPPING, self::SOURCE_INVOICE_LOCATION, self::SOURCE_REPORTING_TAG, self::SOURCE_CUSTOMER_PREFIX, null, ''], true)
                && $customer->branch_mapping_source !== self::SOURCE_ADMIN_OVERRIDE) {
                // Existing verified mapping mismatch
                if ($customer->branch_mapping_source && $customer->branch_mapping_source !== self::SOURCE_UNMAPPED) {
                    return $this->conflictResult(array_merge($base, [
                        'resolved_branch_id' => null,
                        'resolution_source' => self::SOURCE_CONFLICT,
                        'confidence' => 'none',
                        'conflict_type' => CustomerBranchMappingConflict::TYPE_PREFIX_EXISTING_MAPPING_MISMATCH,
                        'explanation' => 'Customer number prefix disagrees with existing verified branch mapping.',
                        'would_change' => true,
                    ]));
                }
            }

            return array_merge($base, [
                'resolved_branch_id' => $prefixBranchId,
                'resolution_source' => self::SOURCE_CUSTOMER_PREFIX,
                'confidence' => $locationBranchId === $prefixBranchId ? 'high' : 'medium',
                'conflict_type' => null,
                'explanation' => 'Resolved from customer number prefix.',
                'would_change' => $existingBranchId !== $prefixBranchId,
            ]);
        }

        // Unknown alphabetic prefix is not an automatic conflict when a later
        // unambiguous source (location / tag / existing) can still resolve the branch.
        $unknownPrefix = $this->matcher->hasUnknownAlphaPrefix($customerNumber);

        // 3. Invoice location
        if ($locationBranchId) {
            return array_merge($base, [
                'resolved_branch_id' => $locationBranchId,
                'resolution_source' => self::SOURCE_INVOICE_LOCATION,
                'confidence' => 'medium',
                'conflict_type' => null,
                'explanation' => $unknownPrefix
                    ? 'Resolved from Zoho invoice location (customer number prefix is not configured).'
                    : 'Resolved from Zoho invoice location.',
                'would_change' => $existingBranchId !== $locationBranchId,
            ]);
        }

        // 4. Reporting tag
        if ($tagBranchId) {
            return array_merge($base, [
                'resolved_branch_id' => $tagBranchId,
                'resolution_source' => self::SOURCE_REPORTING_TAG,
                'confidence' => 'medium',
                'conflict_type' => null,
                'explanation' => $unknownPrefix
                    ? 'Resolved from Zoho reporting tag (customer number prefix is not configured).'
                    : 'Resolved from Zoho reporting tag.',
                'would_change' => $existingBranchId !== $tagBranchId,
            ]);
        }

        // 5. Existing verified mapping
        if ($existingBranchId && ! $customer->is_unmapped) {
            return array_merge($base, [
                'resolved_branch_id' => $existingBranchId,
                'resolution_source' => self::SOURCE_EXISTING_MAPPING,
                'confidence' => 'low',
                'conflict_type' => null,
                'explanation' => $unknownPrefix
                    ? 'Kept existing verified branch mapping (customer number prefix is not configured).'
                    : 'Kept existing verified branch mapping.',
                'would_change' => false,
            ]);
        }

        if ($unknownPrefix) {
            return $this->conflictResult(array_merge($base, [
                'resolved_branch_id' => null,
                'resolution_source' => self::SOURCE_CONFLICT,
                'confidence' => 'none',
                'conflict_type' => CustomerBranchMappingConflict::TYPE_UNKNOWN_PREFIX,
                'explanation' => 'Customer number has an alphabetic prefix that is not configured.',
            ]));
        }

        // 6. Unmapped
        return array_merge($base, [
            'resolved_branch_id' => null,
            'resolution_source' => self::SOURCE_UNMAPPED,
            'confidence' => 'none',
            'conflict_type' => null,
            'explanation' => 'No unambiguous branch mapping source found.',
            'would_change' => false,
        ]);
    }

    /**
     * Apply a resolution to the customer when unambiguous and allowed.
     *
     * @param  array<string, mixed>  $resolution
     */
    public function apply(Customer $customer, array $resolution, ?User $actor = null, bool $dryRun = false, ?string $runId = null): Customer
    {
        $from = $customer->branch_id;
        $to = $resolution['resolved_branch_id'] ?? null;
        $source = (string) ($resolution['resolution_source'] ?? self::SOURCE_UNMAPPED);
        $protected = (bool) ($resolution['protected_history'] ?? false);

        $history = [
            'customer_id' => $customer->id,
            'from_branch_id' => $from,
            'to_branch_id' => $to,
            'resolution_source' => $source,
            'confidence' => $resolution['confidence'] ?? null,
            'detected_prefix' => $resolution['prefix_detected'] ?? null,
            'dry_run' => $dryRun,
            'applied' => false,
            'protected_history' => $protected,
            'evidence' => [
                'invoice_location' => $resolution['invoice_location_evidence'] ?? [],
                'reporting_tag' => $resolution['reporting_tag_evidence'] ?? [],
                'existing' => $resolution['existing_mapping_evidence'] ?? [],
            ],
            'explanation' => $resolution['explanation'] ?? null,
            'actor_id' => $actor?->id,
            'run_id' => $runId,
        ];

        if ($source === self::SOURCE_CONFLICT || ($resolution['conflict_type'] ?? null)) {
            if (! $dryRun) {
                $this->upsertConflict($customer, $resolution);
                // Keep conflicted customers unmapped / not visible to branch users
                if (! $customer->administrator_branch_override) {
                    $customer->forceFill([
                        'is_unmapped' => true,
                        'status' => Customer::STATUS_UNMAPPED,
                        'branch_mapping_source' => self::SOURCE_CONFLICT,
                        'branch_mapping_confidence' => 'none',
                        'branch_mapping_prefix' => $resolution['prefix_detected'] ?? null,
                    ])->save();
                }
            }
            CustomerBranchMappingHistory::query()->create($history);
            $this->audit->log('customer.branch_mapping_conflict', $customer, null, $resolution, $customer->branch_id);

            return $customer->fresh();
        }

        if ($dryRun || $to === null || (int) $from === (int) $to) {
            if ((int) $from === (int) $to && $to !== null && ! $dryRun) {
                $customer->forceFill([
                    'branch_mapping_source' => $source,
                    'branch_mapping_confidence' => $resolution['confidence'] ?? null,
                    'branch_mapping_prefix' => $resolution['prefix_detected'] ?? null,
                    'branch_mapping_confirmed_at' => now(),
                    'is_unmapped' => false,
                ])->save();
            }
            CustomerBranchMappingHistory::query()->create($history);

            return $customer->fresh();
        }

        if ($protected && (int) $from !== (int) $to && $from !== null) {
            $history['explanation'] = 'Protected operational/financial history; manual review required.';
            CustomerBranchMappingHistory::query()->create($history);
            if (! $dryRun) {
                $this->upsertConflict($customer, array_merge($resolution, [
                    'conflict_type' => CustomerBranchMappingConflict::TYPE_PREFIX_EXISTING_MAPPING_MISMATCH,
                    'explanation' => $history['explanation'],
                ]));
            }

            return $customer->fresh();
        }

        if (! $dryRun) {
            DB::transaction(function () use ($customer, $to, $source, $resolution, &$history, $actor) {
                $customer->forceFill([
                    'branch_id' => $to,
                    'is_unmapped' => false,
                    'status' => $customer->status === Customer::STATUS_UNMAPPED
                        ? Customer::STATUS_ACTIVE
                        : $customer->status,
                    'branch_mapping_source' => $source,
                    'branch_mapping_confidence' => $resolution['confidence'] ?? null,
                    'branch_mapping_prefix' => $resolution['prefix_detected'] ?? null,
                    'branch_mapped_at' => now(),
                    'branch_mapping_confirmed_at' => ($resolution['confidence'] ?? '') === 'high' ? now() : null,
                ])->save();

                CustomerBranchMappingConflict::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', CustomerBranchMappingConflict::STATUS_OPEN)
                    ->update([
                        'status' => CustomerBranchMappingConflict::STATUS_RESOLVED,
                        'resolved_at' => now(),
                        'resolved_by' => $actor?->id,
                        'resolution_notes' => 'Auto-resolved by unambiguous prefix/location mapping.',
                    ]);

                $history['applied'] = true;
                try {
                    $this->workQueues->recalculate($customer->fresh());
                } catch (\Throwable) {
                }
            });
            $this->audit->log('customer.branch_mapped', $customer, ['branch_id' => $from], [
                'branch_id' => $to,
                'source' => $source,
            ], $to);
        }

        CustomerBranchMappingHistory::query()->create($history);

        return $customer->fresh();
    }

    public function setAdministratorOverride(Customer $customer, Branch|int $branch, User $actor, ?string $reason = null): Customer
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $from = $customer->branch_id;
        $customer->forceFill([
            'branch_id' => $branchId,
            'is_unmapped' => false,
            'status' => $customer->status === Customer::STATUS_UNMAPPED ? Customer::STATUS_ACTIVE : $customer->status,
            'administrator_branch_override' => true,
            'branch_override_by' => $actor->id,
            'branch_mapping_source' => self::SOURCE_ADMIN_OVERRIDE,
            'branch_mapping_confidence' => 'high',
            'branch_mapped_at' => now(),
        ])->save();

        CustomerBranchMappingHistory::query()->create([
            'customer_id' => $customer->id,
            'from_branch_id' => $from,
            'to_branch_id' => $branchId,
            'resolution_source' => self::SOURCE_ADMIN_OVERRIDE,
            'confidence' => 'high',
            'applied' => true,
            'explanation' => $reason ?: 'Administrator override',
            'actor_id' => $actor->id,
            'run_id' => 'admin-'.Str::uuid(),
        ]);

        CustomerBranchMappingConflict::query()
            ->where('customer_id', $customer->id)
            ->where('status', CustomerBranchMappingConflict::STATUS_OPEN)
            ->update([
                'status' => CustomerBranchMappingConflict::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
                'resolution_notes' => 'Resolved by administrator override.',
            ]);

        $this->audit->log('customer.branch_admin_override', $customer, null, [
            'branch_id' => $branchId,
            'reason' => $reason,
        ], $branchId);

        return $customer->fresh();
    }

    public function clearAdministratorOverride(Customer $customer, User $actor): Customer
    {
        $customer->forceFill([
            'administrator_branch_override' => false,
            'branch_override_by' => null,
        ])->save();
        $this->audit->log('customer.branch_admin_override_cleared', $customer, null, [], $customer->branch_id, null, $actor->id ?? null);

        return $customer->fresh();
    }

    public function hasProtectedHistory(Customer $customer): bool
    {
        $id = (int) $customer->id;

        return Payment::withoutGlobalScopes()->where('customer_id', $id)->whereNotNull('confirmed_at')->exists()
            || Receipt::withoutGlobalScopes()->whereHas('payment', fn ($q) => $q->withoutGlobalScopes()->where('customer_id', $id))->exists()
            || CustomerCollectorOwnership::query()->where('customer_id', $id)->where('is_active', true)->exists()
            || TemporaryCollectionAssignment::query()->where('customer_id', $id)->where('status', 'active')->exists()
            || Payment::withoutGlobalScopes()->where('customer_id', $id)->whereIn('handover_status', ['handed_over', 'partially_handed_over'])->exists()
            || CustomerAssignment::withoutGlobalScopes()->where('customer_id', $id)->where('is_active', true)->exists()
            || PromiseToPay::withoutGlobalScopes()->where('customer_id', $id)->whereIn('status', ['active', 'due_soon', 'due_today', 'overdue'])->exists();
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    public function upsertConflict(Customer $customer, array $resolution): CustomerBranchMappingConflict
    {
        return CustomerBranchMappingConflict::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'status' => CustomerBranchMappingConflict::STATUS_OPEN,
                'conflict_type' => $resolution['conflict_type'] ?? CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH,
            ],
            [
                'prefix_branch_id' => $resolution['prefix_branch_id']
                    ?? ($resolution['resolved_branch_id'] ?? null),
                'location_branch_id' => $resolution['invoice_location_evidence']['primary_branch_id'] ?? null,
                'existing_branch_id' => $customer->branch_id,
                'detected_prefix' => $resolution['prefix_detected'] ?? null,
                'customer_number' => $customer->customer_number,
                'evidence' => [
                    'invoice_location' => $resolution['invoice_location_evidence'] ?? [],
                    'reporting_tag' => $resolution['reporting_tag_evidence'] ?? [],
                    'existing' => $resolution['existing_mapping_evidence'] ?? [],
                ],
                'explanation' => $resolution['explanation'] ?? null,
            ]
        );
    }

    /**
     * @return array{branch_ids:list<int>,primary_branch_id:?int,locations:list<string>}
     */
    protected function invoiceLocationEvidence(Customer $customer): array
    {
        $locations = Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereNotNull('zoho_location_id')
            ->where('zoho_location_id', '!=', '')
            ->select('zoho_location_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('zoho_location_id')
            ->orderByDesc('aggregate')
            ->get();

        $branchIds = [];
        $primary = null;
        $locationIds = [];
        foreach ($locations as $row) {
            $locationIds[] = (string) $row->zoho_location_id;
            $branchId = $this->zohoMapping->resolveBranchId(['location_id' => $row->zoho_location_id]);
            if ($branchId) {
                $branchIds[] = $branchId;
                $primary ??= $branchId;
            }
        }

        return [
            'branch_ids' => array_values(array_unique($branchIds)),
            'primary_branch_id' => $primary,
            'locations' => $locationIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function conflictResult(array $payload): array
    {
        $payload['resolution_source'] = self::SOURCE_CONFLICT;

        return $payload;
    }
}
