<?php

namespace App\Services\Reports;

use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\PromiseToPay;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentReportService
{
    public function assignmentsByCollector(?int $branchId = null): Collection
    {
        $query = CustomerAssignment::query()
            ->selectRaw('collector_id, status, count(*) as total, sum(debt_snapshot_outstanding) as total_outstanding')
            ->groupBy('collector_id', 'status')
            ->with('collector.user:id,name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    public function visitsByOutcome(?int $branchId = null): Collection
    {
        $query = CollectionVisit::query()
            ->selectRaw('outcome, count(*) as total')
            ->groupBy('outcome');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    public function overduePromises(?int $branchId = null): Collection
    {
        $query = PromiseToPay::query()
            ->with(['customer:id,contact_name', 'collector.user:id,name'])
            ->where('status', PromiseToPay::STATUS_OVERDUE);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('promised_date')->get();
    }

    public function unassignedDebtors(?int $branchId = null): Collection
    {
        $query = Customer::query()
            ->where('status', Customer::STATUS_ACTIVE)
            ->where('is_unmapped', false)
            ->where('outstanding_receivable', '>', 0)
            ->whereDoesntHave('assignments', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderByDesc('outstanding_receivable')->get();
    }

    public function gpsMismatch(?int $branchId = null): Collection
    {
        $query = CollectionVisit::query()
            ->with(['customer:id,contact_name', 'collector.user:id,name'])
            ->whereIn('gps_risk_level', [CollectionVisit::GPS_WARNING, CollectionVisit::GPS_HIGH_RISK]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderByDesc('visited_at')->get();
    }

    public function toCsv(iterable $rows, array $headers, callable $mapper): StreamedResponse
    {
        return response()->stream(function () use ($rows, $headers, $mapper) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $mapper($row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report.csv"',
        ]);
    }
}
