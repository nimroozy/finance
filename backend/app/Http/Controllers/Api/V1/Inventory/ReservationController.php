<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Reservation;
use App\Services\Inventory\ReservationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);
        $user = Auth::user();
        $q = Reservation::query()->with('items');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($qq) => $qq->where('status', $request->string('status')))
            ->when($request->filled('installation_id'), fn ($qq) => $qq->where('installation_id', $request->integer('installation_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Reservation::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
        ]);
        try {
            $rsv = $this->reservations->create($data, $data['items'], $request->user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($rsv, null, 201);
    }

    public function show(int $id): JsonResponse
    {
        $rsv = Reservation::with('items.product')->findOrFail($id);
        $this->authorize('view', $rsv);

        return ApiResponse::success($rsv);
    }

    public function reserve(Request $request, int $id): JsonResponse
    {
        $rsv = Reservation::with('items')->findOrFail($id);
        $this->authorize('update', $rsv);
        try {
            return ApiResponse::success($this->reservations->reserve($rsv, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function fulfill(Request $request, int $id): JsonResponse
    {
        $rsv = Reservation::with('items')->findOrFail($id);
        $this->authorize('update', $rsv);
        $opts = $request->validate([
            'custody_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
        ]);
        try {
            return ApiResponse::success($this->reservations->fulfill($rsv, $request->user(), $opts));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function release(Request $request, int $id): JsonResponse
    {
        $rsv = Reservation::with('items')->findOrFail($id);
        $this->authorize('update', $rsv);
        try {
            return ApiResponse::success($this->reservations->release($rsv, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}