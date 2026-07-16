<?php

namespace App\Services\Zoho;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerBranchChangeLog;
use App\Models\Invoice;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

class ZohoBranchReprocessService
{
    public function __construct(
        protected ZohoBranchMappingService $mapping,
        protected AuditLogger $audit,
    ) {}

    public function customers(bool $apply = false, ?int $branchId = null, ?int $customerId = null, int $limit = 500, ?string $locationId = null): array
    {
        $stats = ['scanned' => 0, 'unchanged' => 0, 'would_change' => 0, 'changed' => 0, 'conflicts' => 0];
        $locationIdsForBranch = $branchId
            ? $this->locationIdsForBranch($branchId)
            : [];

        Customer::withoutGlobalScopes()
            ->when($customerId, fn (Builder $query) => $query->whereKey($customerId))
            ->when($locationId, function (Builder $query) use ($locationId) {
                $query->where(function (Builder $inner) use ($locationId) {
                    $inner->where('zoho_location_id', $locationId)
                        ->orWhereHas('invoices', fn (Builder $invoices) => $invoices->withoutGlobalScopes()->where('zoho_location_id', $locationId));
                });
            })
            ->when($branchId && $locationIdsForBranch === [], fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($branchId && $locationIdsForBranch !== [], function (Builder $query) use ($branchId, $locationIdsForBranch) {
                $query->where(function (Builder $inner) use ($branchId, $locationIdsForBranch) {
                    $inner->where('branch_id', $branchId)
                        ->orWhereIn('zoho_location_id', $locationIdsForBranch)
                        ->orWhereHas('invoices', function (Builder $invoiceQuery) use ($locationIdsForBranch) {
                            $invoiceQuery->withoutGlobalScopes()->whereIn('zoho_location_id', $locationIdsForBranch);
                        });
                });
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (Customer $customer) use ($apply, $branchId, &$stats) {
                $stats['scanned']++;
                $newBranchId = $this->mapping->resolveCustomerBranchId($customer);
                if ($branchId !== null && $newBranchId !== null && $newBranchId !== $branchId) {
                    // When filtering to one branch, skip records that resolve elsewhere.
                    $stats['unchanged']++;

                    return;
                }
                if ($newBranchId === null || $newBranchId === $customer->branch_id) {
                    $stats['unchanged']++;

                    return;
                }
                $stats['would_change']++;
                if ($this->mapping->customerHasBranchConflict($customer, $newBranchId)) {
                    $stats['conflicts']++;
                    if ($apply) {
                        CustomerBranchChangeLog::query()->create([
                            'customer_id' => $customer->id,
                            'old_branch_id' => $customer->branch_id,
                            'new_branch_id' => $newBranchId,
                            'source' => 'reprocess_conflict',
                            'actor_type' => 'system',
                            'reason' => 'Customer has payment or assignment history; branch change skipped.',
                            'meta' => ['dry_run' => false],
                        ]);
                    }

                    return;
                }
                if (! $apply) {
                    return;
                }
                $oldBranchId = $customer->branch_id;
                $customer->update([
                    'branch_id' => $newBranchId,
                    'is_unmapped' => false,
                    'status' => $customer->status === Customer::STATUS_UNMAPPED
                        ? Customer::STATUS_ACTIVE
                        : $customer->status,
                ]);
                CustomerBranchChangeLog::query()->create([
                    'customer_id' => $customer->id,
                    'old_branch_id' => $oldBranchId,
                    'new_branch_id' => $newBranchId,
                    'source' => 'reprocess',
                    'actor_type' => 'system',
                    'reason' => 'Applied current Zoho branch mapping.',
                    'meta' => ['dry_run' => false],
                ]);
                $this->audit->log(
                    'customer.branch.reprocessed',
                    $customer,
                    ['branch_id' => $oldBranchId],
                    ['branch_id' => $newBranchId],
                    $newBranchId
                );
                $stats['changed']++;
            });

        return $stats;
    }

    public function invoices(bool $apply = false, ?int $branchId = null, ?int $customerId = null, int $limit = 500, ?string $locationId = null): array
    {
        $stats = ['scanned' => 0, 'unchanged' => 0, 'would_change' => 0, 'changed' => 0];
        $locationIdsForBranch = $branchId
            ? $this->locationIdsForBranch($branchId)
            : [];

        Invoice::withoutGlobalScopes()->with('customer')
            ->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->when($locationId, fn (Builder $query) => $query->where('zoho_location_id', $locationId))
            ->when($branchId && $locationIdsForBranch === [], fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($branchId && $locationIdsForBranch !== [], function (Builder $query) use ($branchId, $locationIdsForBranch) {
                $query->where(function (Builder $inner) use ($branchId, $locationIdsForBranch) {
                    $inner->where('branch_id', $branchId)
                        ->orWhereIn('zoho_location_id', $locationIdsForBranch)
                        ->orWhereNull('branch_id');
                });
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (Invoice $invoice) use ($apply, $branchId, &$stats) {
                $stats['scanned']++;
                $payload = [
                    'location_id' => $invoice->zoho_location_id,
                    'reporting_tags' => $invoice->reporting_tags ?? [],
                ];
                $newBranchId = $this->mapping->resolveBranchId($payload)
                    ?? $invoice->customer?->branch_id;
                if ($branchId !== null && $newBranchId !== null && $newBranchId !== $branchId) {
                    $stats['unchanged']++;

                    return;
                }
                if ($newBranchId === null || $newBranchId === $invoice->branch_id) {
                    $stats['unchanged']++;

                    return;
                }
                $stats['would_change']++;
                if ($apply) {
                    $invoice->update(['branch_id' => $newBranchId]);
                    $stats['changed']++;
                }
            });

        return $stats;
    }

    /**
     * @return list<string>
     */
    protected function locationIdsForBranch(int $branchId): array
    {
        $branch = Branch::withoutGlobalScopes()->find($branchId);
        $ids = [];
        if ($branch?->zoho_location_id) {
            $ids[] = (string) $branch->zoho_location_id;
        }

        return array_values(array_unique($ids));
    }
}
