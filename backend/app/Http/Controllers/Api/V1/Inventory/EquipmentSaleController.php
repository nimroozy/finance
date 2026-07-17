<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\EquipmentSale;
use App\Services\Inventory\EquipmentSaleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class EquipmentSaleController extends Controller
{
    public function __construct(private EquipmentSaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipmentSale::class);
        $user = Auth::user();
        $q = EquipmentSale::query()->with('items');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EquipmentSale::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
        ]);
        try {
            return ApiResponse::success($this->sales->create($data, $data['items'], $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function issue(Request $request, int $id): JsonResponse
    {
        $sale = EquipmentSale::with('items')->findOrFail($id);
        $this->authorize('update', $sale);
        try {
            return ApiResponse::success($this->sales->reserveAndIssue($sale, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}