<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmDashboardController extends Controller
{
    public function __construct(private CrmDashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('crm.reports.view')
            || Auth::user()->can('crm.leads.view')
            || Auth::user()->can('dashboard.view'),
            403
        );

        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $user = Auth::user();
        if ($branchId === null && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $ids = $user->branchIds();
            $branchId = $ids[0] ?? null;
        }

        return ApiResponse::success($this->dashboard->summary($branchId, $user));
    }
}
