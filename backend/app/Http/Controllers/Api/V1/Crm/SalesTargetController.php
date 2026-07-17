<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\SalesTarget;
use App\Services\Crm\SalesTargetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SalesTargetController extends Controller
{
    public function __construct(private SalesTargetService $targets) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.targets.view') || Auth::user()->can('crm.targets.manage'), 403);

        $query = SalesTarget::query()
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('period_year'), fn ($q) => $q->where('period_year', $request->integer('period_year')))
            ->when($request->filled('period_month'), fn ($q) => $q->where('period_month', $request->integer('period_month')))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.targets.manage'), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'leads_target' => ['nullable', 'integer', 'min:0'],
            'qualified_target' => ['nullable', 'integer', 'min:0'],
            'won_target' => ['nullable', 'integer', 'min:0'],
            'revenue_target' => ['nullable', 'numeric'],
            'installation_fee_target' => ['nullable', 'numeric'],
            'mrr_target' => ['nullable', 'numeric'],
        ]);

        try {
            $target = $this->targets->upsert($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($target, null, 201);
    }
}
