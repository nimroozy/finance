<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Transfer;
use App\Services\Inventory\TransferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class TransferController extends Controller
{
    public function __construct(private TransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transfer::class);
        $user = Auth::user();
        $q = Transfer::query()->with('items');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($qq) => $qq->where('status', $request->string('status')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'from_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'to_location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
        ]);
        try {
            return ApiResponse::success($this->transfers->create($data, $data['items'], $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $t = Transfer::query()->findOrFail($id);
        $this->authorize('update', $t);
        try {
            return ApiResponse::success($this->transfers->submit($t, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $t = Transfer::query()->findOrFail($id);
        $this->authorize('approve', $t);
        try {
            return ApiResponse::success($this->transfers->approve($t, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function dispatchTransfer(Request $request, int $id): JsonResponse
    {
        $t = Transfer::with('items')->findOrFail($id);
        $this->authorize('update', $t);
        try {
            return ApiResponse::success($this->transfers->dispatch($t, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $t = Transfer::with('items')->findOrFail($id);
        $this->authorize('update', $t);
        $data = $request->validate([
            'received_qtys' => ['nullable', 'array'],
            'received_qtys.*' => ['numeric', 'min:0'],
        ]);
        try {
            return ApiResponse::success($this->transfers->receive($t, $request->user(), $data['received_qtys'] ?? null));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $t = Transfer::query()->findOrFail($id);
        $this->authorize('update', $t);
        try {
            return ApiResponse::success($this->transfers->close($t, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}