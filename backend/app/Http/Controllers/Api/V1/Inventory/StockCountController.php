<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockCount;
use App\Services\Inventory\StockCountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class StockCountController extends Controller
{
    public function __construct(private StockCountService $counts) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockCount::class);
        $user = Auth::user();
        $q = StockCount::query()->with('lines');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StockCount::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['required', 'integer', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->counts->create($data, $request->user()), null, 201);
    }

    public function record(Request $request, int $id): JsonResponse
    {
        $count = StockCount::with('lines')->findOrFail($id);
        $this->authorize('update', $count);
        $data = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['numeric'],
        ]);

        return ApiResponse::success($this->counts->recordCounts($count, $data['counted']));
    }

    public function post(Request $request, int $id): JsonResponse
    {
        $count = StockCount::with('lines')->findOrFail($id);
        $this->authorize('update', $count);
        try {
            return ApiResponse::success($this->counts->post($count, $request->user()));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }
}