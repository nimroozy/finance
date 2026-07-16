<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashHandoverRequest;
use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PromiseToPay;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->isSuperAdmin() || $user->isCentralFinanceAdmin() ? null : $user->branchIds();
        $collectorId = $user->isCollectorOnly() ? $user->collector?->id : null;
        $scope = function (Builder $query) use ($branchIds): Builder {
            return $branchIds === null ? $query : $query->whereIn('branch_id', $branchIds);
        };
        $collectorScope = function (Builder $query) use ($scope, $collectorId): Builder {
            $scope($query);

            return $collectorId ? $query->where('collector_id', $collectorId) : $query;
        };

        $invoiceQuery = $scope(Invoice::withoutGlobalScopes());
        $assignmentQuery = $collectorScope(CustomerAssignment::withoutGlobalScopes());
        $paymentQuery = $collectorScope(Payment::withoutGlobalScopes());
        $visitQuery = $collectorScope(CollectionVisit::withoutGlobalScopes());
        $promiseQuery = $collectorScope(PromiseToPay::withoutGlobalScopes());
        $handoverQuery = $collectorScope(CashHandoverRequest::withoutGlobalScopes());

        return ApiResponse::success([
            'role' => $user->isSuperAdmin() ? 'super_admin' : ($user->isBranchManager() ? 'branch_manager' : ($user->isCollectorOnly() ? 'collector' : 'finance')),
            'scope' => [
                'branch_ids' => $branchIds,
                'collector_id' => $collectorId,
            ],
            'customers' => [
                'total' => $scope(Customer::withoutGlobalScopes())->count(),
                'unmapped' => $scope(Customer::withoutGlobalScopes())->where('is_unmapped', true)->count(),
            ],
            'invoices' => [
                'total' => (clone $invoiceQuery)->count(),
                'open' => (clone $invoiceQuery)->where('balance', '>', 0)->count(),
                'overdue' => (clone $invoiceQuery)->where('balance', '>', 0)->whereDate('due_date', '<', today())->count(),
                'outstanding_amount' => (string) (clone $invoiceQuery)->where('balance', '>', 0)->sum('balance'),
            ],
            'assignments' => [
                'total' => (clone $assignmentQuery)->count(),
                'active' => (clone $assignmentQuery)->where('is_active', true)->count(),
            ],
            'visits' => [
                'today' => (clone $visitQuery)->whereDate('visited_at', today())->count(),
                'total' => (clone $visitQuery)->count(),
            ],
            'promises' => [
                'open' => (clone $promiseQuery)->whereIn('status', PromiseToPay::OPEN_STATUSES)->count(),
                'overdue' => (clone $promiseQuery)->where('status', PromiseToPay::STATUS_OVERDUE)->count(),
            ],
            'payments' => [
                'total' => (clone $paymentQuery)->count(),
                'confirmed' => (clone $paymentQuery)->whereIn('status', Payment::CONFIRMED_STATUSES)->count(),
                'sync_failed' => (clone $paymentQuery)->where('zoho_sync_status', Payment::ZOHO_FAILED)->count(),
            ],
            'cash_custody' => [
                'pending_handovers' => (clone $handoverQuery)->whereIn('status', ['draft', 'submitted'])->count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
