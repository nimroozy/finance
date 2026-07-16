<?php

namespace App\Http\Controllers\Api\V1\Ownership;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashHandoverRequest;
use App\Models\CollectorWallet;
use App\Models\Customer;
use App\Models\CustomerCollectorOwnership;
use App\Models\CustomerWorkQueue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PromiseToPay;
use App\Models\TemporaryCollectionAssignment;
use App\Models\ZohoSyncJob;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchReceivablesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchIds = $request->user()->isSuperAdmin() || $request->user()->isCentralFinanceAdmin()
            ? Branch::withoutGlobalScopes()->pluck('id')
            : $request->user()->branches()->pluck('branches.id');

        $rows = [];
        foreach ($branchIds as $branchId) {
            $branch = Branch::withoutGlobalScopes()->find($branchId);
            if (! $branch) continue;
            $open = Invoice::withoutGlobalScopes()->where('branch_id', $branchId)->where('balance', '>', 0);
            $total = (clone $open)->sum('balance');
            $debtors = (int) Invoice::withoutGlobalScopes()->where('branch_id', $branchId)->where('balance', '>', 0)->selectRaw('count(distinct customer_id) as c')->value('c');
            $owned = CustomerCollectorOwnership::query()->active()->where('branch_id', $branchId)->count();
            $temp = TemporaryCollectionAssignment::query()->currentlyActive()->where('branch_id', $branchId)->count();
            $unassigned = CustomerWorkQueue::query()->where('branch_id', $branchId)->where('ownership_source', 'unassigned')->count();
            $assignedQueue = CustomerWorkQueue::query()->where('branch_id', $branchId)->whereNotNull('effective_collector_id')->count();
            $collectedToday = Payment::withoutGlobalScopes()->where('branch_id', $branchId)->whereDate('confirmed_at', today())->sum('amount');
            $collectedMonth = Payment::withoutGlobalScopes()->where('branch_id', $branchId)->whereBetween('confirmed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
            $cashHeld = CollectorWallet::withoutGlobalScopes()->where('branch_id', $branchId)->sum('balance');
            $pendingHandovers = CashHandoverRequest::withoutGlobalScopes()->where('branch_id', $branchId)->whereIn('status', ['submitted', 'draft'])->count();
            $overduePromises = PromiseToPay::withoutGlobalScopes()->where('branch_id', $branchId)->where('status', 'overdue')->count();
            $lastSync = ZohoSyncJob::query()->whereIn('type', ['invoices', 'customers'])->where('status', 'completed')->orderByDesc('finished_at')->value('finished_at');
            $byCollector = CustomerWorkQueue::query()
                ->select('effective_collector_id', DB::raw('SUM(total_open_balance) as amount'), DB::raw('COUNT(*) as customers'))
                ->where('branch_id', $branchId)->whereNotNull('effective_collector_id')
                ->groupBy('effective_collector_id')->get();

            $rows[] = [
                'branch' => $branch->only(['id', 'code', 'name_en', 'name_fa']),
                'total_receivable' => Money::normalize((string) $total),
                'active_debtor_customers' => $debtors,
                'open_invoice_count' => (clone $open)->count(),
                'assigned_customers' => $assignedQueue,
                'unassigned_customers' => $unassigned,
                'permanently_owned_customers' => $owned,
                'temporarily_assigned_customers' => $temp,
                'amount_by_collector' => $byCollector,
                'collected_today' => Money::normalize((string) $collectedToday),
                'collected_this_month' => Money::normalize((string) $collectedMonth),
                'cash_held_by_collectors' => Money::normalize((string) $cashHeld),
                'pending_handovers' => $pendingHandovers,
                'overdue_promises' => $overduePromises,
                'sync_freshness' => $lastSync,
            ];
        }

        return ApiResponse::success($rows);
    }
}
