<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboards\OperationsDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperationsDashboardController extends Controller
{
    public function __construct(private OperationsDashboardService $dashboard) {}

    public function support(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('tickets.view') || Auth::user()->can('dashboard.view'), 403);

        return ApiResponse::success($this->dashboard->supportSummary($this->branchIds($request)));
    }

    public function technical(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('tasks.view') || Auth::user()->can('dashboard.view'), 403);

        return ApiResponse::success($this->dashboard->technicalSummary($this->branchIds($request)));
    }

    public function noc(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('escalations.view') || Auth::user()->can('dashboard.view'), 403);

        return ApiResponse::success($this->dashboard->nocSummary($this->branchIds($request)));
    }

    public function finance(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('installations.view') || Auth::user()->can('dashboard.view'), 403);

        return ApiResponse::success($this->dashboard->financeSummary($this->branchIds($request)));
    }

    public function manager(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('dashboard.view'), 403);

        return ApiResponse::success($this->dashboard->managerSummary($this->branchIds($request)));
    }

    /** @return list<int>|null */
    private function branchIds(Request $request): ?array
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            if ($request->filled('branch_id')) {
                return [$request->integer('branch_id')];
            }

            return null;
        }

        $ids = $user->branchIds();
        if ($request->filled('branch_id')) {
            $requested = $request->integer('branch_id');

            return in_array($requested, $ids, true) ? [$requested] : [];
        }

        return $ids;
    }
}
