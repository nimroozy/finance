<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Repair;
use App\Services\Inventory\RepairService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class RepairController extends Controller
{
    public function __construct(private RepairService $repairs) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Repair::class);
        $user = Auth::user();
        $q = Repair::query()->with('equipment');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function open(Request $request): JsonResponse
    {
        $this->authorize('create', Repair::class);
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', 'exists:inventory_equipment,id'],
            'repair_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'from_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'fault_description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        try {
            return ApiResponse::success($this->repairs->open($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $repair = Repair::query()->findOrFail($id);
        $this->authorize('update', $repair);
        $data = $request->validate([
            'return_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'resolution_notes' => ['nullable', 'string'],
            'repair_cost' => ['nullable', 'numeric'],
        ]);
        try {
            return ApiResponse::success($this->repairs->complete(
                $repair,
                (int) $data['return_location_id'],
                $request->user(),
                $data['resolution_notes'] ?? null,
                isset($data['repair_cost']) ? (float) $data['repair_cost'] : null
            ));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}