<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Services\Inventory\AdjustmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class StockController extends Controller
{
    public function __construct(private AdjustmentService $adjustments) {}

    public function balances(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockBalance::class);
        $user = Auth::user();
        $q = StockBalance::query()->with(['product:id,code,name_en,tracking_mode', 'location:id,code,name,type']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('location_id'), fn ($qq) => $qq->where('location_id', $request->integer('location_id')))
            ->when($request->filled('product_id'), fn ($qq) => $qq->where('product_id', $request->integer('product_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
        $page->getCollection()->transform(function (StockBalance $b) {
            $arr = $b->toArray();
            $arr['available'] = $b->available();

            return $arr;
        });

        return ApiResponse::paginated($page);
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransaction::class);
        $user = Auth::user();
        $q = StockTransaction::query()->with(['product:id,code,name_en']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('type'), fn ($qq) => $qq->where('type', $request->string('type')))
            ->when($request->filled('product_id'), fn ($qq) => $qq->where('product_id', $request->integer('product_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 25));

        return ApiResponse::paginated($page);
    }

    public function adjust(Request $request): JsonResponse
    {
        $this->authorize('adjust', StockBalance::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'qty' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $txn = $this->adjustments->adjust($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($txn, null, 201);
    }
}