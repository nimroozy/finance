<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\MaintenancePlan;
use App\Services\Inventory\MaintenanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenancePlan::class);
        $user = Auth::user();
        $q = MaintenancePlan::query();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MaintenancePlan::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
            'fixed_asset_id' => ['nullable', 'integer', 'exists:inventory_fixed_assets,id'],
            'tower_id' => ['nullable', 'integer', 'exists:inventory_towers,id'],
            'site_id' => ['nullable', 'integer', 'exists:inventory_sites,id'],
            'frequency' => ['nullable', 'string', 'max:32'],
            'next_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->maintenance->createPlan($data, $request->user()), null, 201);
    }

    public function createTask(Request $request, int $id): JsonResponse
    {
        $plan = MaintenancePlan::query()->findOrFail($id);
        $this->authorize('update', $plan);

        return ApiResponse::success($this->maintenance->createTaskFromPlan($plan, $request->user()));
    }
}