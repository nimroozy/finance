<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Equipment;
use App\Services\Inventory\EquipmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class EquipmentController extends Controller
{
    public function __construct(private EquipmentService $equipment) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);
        $user = Auth::user();
        $q = Equipment::query()->with(['product:id,code,name_en', 'location:id,code,name']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($qq) => $qq->where('status', $request->string('status')))
            ->when($request->filled('product_id'), fn ($qq) => $qq->where('product_id', $request->integer('product_id')))
            ->when($request->filled('search'), function ($qq) use ($request) {
                $t = '%'.$request->string('search').'%';
                $qq->where(fn ($w) => $w->where('serial_number', 'like', $t)->orWhere('equipment_number', 'like', $t)->orWhere('mac_address', 'like', $t));
            });
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function receive(Request $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'serial_number' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'mac_address' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'integer', 'exists:inventory_suppliers,id'],
            'purchase_cost' => ['nullable', 'numeric'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->equipment->receiveSerial($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);
        $data = $request->validate([
            'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'idempotency_key' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->equipment->move($eq, (int) $data['to_location_id'], $request->user(), $data['idempotency_key'] ?? null));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $eq = Equipment::with(['product', 'location', 'customer'])->findOrFail($id);
        $this->authorize('view', $eq);

        return ApiResponse::success($eq);
    }
}