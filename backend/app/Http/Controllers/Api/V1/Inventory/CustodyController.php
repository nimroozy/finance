<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\CustodyRecord;
use App\Services\Inventory\CustodyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CustodyController extends Controller
{
    public function __construct(private CustodyService $custody) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustodyRecord::class);
        $user = Auth::user();
        $q = CustodyRecord::query()->with(['user:id,name', 'product:id,code,name_en']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function issue(Request $request): JsonResponse
    {
        $this->authorize('create', CustodyRecord::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
            'from_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'custody_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'qty' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->custody->issue($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function returnItem(Request $request, int $id): JsonResponse
    {
        $record = CustodyRecord::query()->findOrFail($id);
        $this->authorize('update', $record);
        $data = $request->validate([
            'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
        ]);
        try {
            return ApiResponse::success($this->custody->returnItem($record, (int) $data['to_location_id'], $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}