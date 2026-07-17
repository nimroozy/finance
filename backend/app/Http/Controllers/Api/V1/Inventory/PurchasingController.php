<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseRequest;
use App\Services\Inventory\PurchasingService;
use App\Services\Inventory\ReceivingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class PurchasingController extends Controller
{
    public function __construct(
        private PurchasingService $purchasing,
        private ReceivingService $receiving,
    ) {}

    public function requests(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $user = Auth::user();
        $q = PurchaseRequest::query()->with('items');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->purchasing->createRequest($data, $data['items'], $request->user()), null, 201);
    }

    public function approveRequest(Request $request, int $id): JsonResponse
    {
        $pr = PurchaseRequest::query()->findOrFail($id);
        $this->authorize('approve', $pr);

        return ApiResponse::success($this->purchasing->approveRequest($pr, $request->user()));
    }

    public function orders(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $user = Auth::user();
        $q = PurchaseOrder::query()->with(['items', 'supplier:id,code,name']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:inventory_suppliers,id'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:inventory_purchase_requests,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric'],
        ]);
        try {
            return ApiResponse::success($this->purchasing->createOrder($data, $data['items'], $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function approveOrder(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::query()->findOrFail($id);
        $this->authorize('approve', $po);

        return ApiResponse::success($this->purchasing->approveOrder($po, $request->user()));
    }

    public function receive(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:inventory_purchase_orders,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:inventory_suppliers,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric'],
            'items.*.serial_number' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->receiving->createAndPost($data, $data['items'], $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}