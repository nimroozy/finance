<?php

namespace App\Services\Zoho;

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

    public function customers(bool $apply = false, ?int $branchId = null, ?int $customerId = null, int $limit = 500): array
    {
        $stats = ['scanned' => 0, 'unchanged' => 0, 'would_change' => 0, 'changed' => 0, 'conflicts' => 0];
        Customer::withoutGlobalScopes()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($customerId, fn (Builder $query) => $query->whereKey($customerId))
            ->limit(max(1, $limit))
            ->get()
            ->each(function (Customer $customer) use ($apply, &$stats) {
                $stats['scanned']++;
                $newBranchId = $this->mapping->resolveBranchId(['reporting_tags' => $customer->reporting_tags ?? []]);
                if ($newBranchId === null || $newBranchId === $customer->branch_id) {
                    $stats['unchanged']++;

                    return;
                }
                $stats['would_change']++;
                if ($this->mapping->customerHasBranchConflict($customer, $newBranchId)) {
                    $stats['conflicts']++;
                    if ($apply) {
                        CustomerBranchChangeLog::query()->create([
                            'customer_id' => $customer->id, 'old_branch_id' => $customer->branch_id,
                            'new_branch_id' => $newBranchId, 'source' => 'reprocess_conflict',
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
                $customer->update(['branch_id' => $newBranchId, 'is_unmapped' => false]);
                CustomerBranchChangeLog::query()->create([
                    'customer_id' => $customer->id, 'old_branch_id' => $oldBranchId,
                    'new_branch_id' => $newBranchId, 'source' => 'reprocess',
                    'reason' => 'Applied current Zoho branch mapping.', 'meta' => ['dry_run' => false],
                ]);
                $this->audit->log('customer.branch.reprocessed', $customer, ['branch_id' => $oldBranchId], ['branch_id' => $newBranchId], $newBranchId);
                $stats['changed']++;
            });

        return $stats;
    }

    public function invoices(bool $apply = false, ?int $branchId = null, ?int $customerId = null, int $limit = 500): array
    {
        $stats = ['scanned' => 0, 'unchanged' => 0, 'would_change' => 0, 'changed' => 0];
        Invoice::withoutGlobalScopes()->with('customer')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($customerId, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->limit(max(1, $limit))->get()
            ->each(function (Invoice $invoice) use ($apply, &$stats) {
                $stats['scanned']++;
                $newBranchId = $this->mapping->resolveBranchId(['reporting_tags' => $invoice->reporting_tags ?? []])
                    ?? $invoice->customer?->branch_id;
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
}
