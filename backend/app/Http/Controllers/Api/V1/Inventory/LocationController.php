<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Location;
use App\Services\Inventory\LocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class LocationController extends Controller
{
    public function __construct(private LocationService $locations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Location::class);
        $user = Auth::user();
        $q = Location::query();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('type'), fn ($qq) => $qq->where('type', $request->string('type')));
        $page = $q->orderBy('code')->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Location::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'type' => ['required', Rule::in(Location::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'custodian_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'allow_sales' => ['nullable', 'boolean'],
            'allow_receiving' => ['nullable', 'boolean'],
            'allow_dispatch' => ['nullable', 'boolean'],
            'allow_negative' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        try {
            return ApiResponse::success($this->locations->create($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $loc = Location::query()->findOrFail($id);
        $this->authorize('update', $loc);
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'type' => ['sometimes', Rule::in(Location::TYPES)],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'custodian_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'allow_sales' => ['nullable', 'boolean'],
            'allow_receiving' => ['nullable', 'boolean'],
            'allow_dispatch' => ['nullable', 'boolean'],
            'allow_negative' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->locations->update($loc, $data, $request->user()));
    }
}