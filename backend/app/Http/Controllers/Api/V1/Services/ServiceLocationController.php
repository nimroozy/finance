<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Models\Services\ServiceLocation;
use App\Services\ServiceLifecycle\ServiceLocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ServiceLocationController extends Controller
{
    public function __construct(private ServiceLocationService $locations) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.locations.view') || Auth::user()?->can('services.view'), 403);
        $query = ServiceLocation::query();
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }
        $query->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')));

        return ApiResponse::paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.locations.manage') || Auth::user()?->can('services.create'), 403);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'contact_name' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'access_instructions' => ['nullable', 'string'],
            'rooftop_access' => ['nullable', 'boolean'],
            'power_availability' => ['nullable', 'string'],
            'building_type' => ['nullable', 'string'],
            'coverage_notes' => ['nullable', 'string'],
        ]);

        try {
            $location = $this->locations->create($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($location, null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.locations.manage') || Auth::user()?->can('services.update'), 403);
        $location = ServiceLocation::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'contact_name' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string'],
            'whatsapp' => ['nullable', 'string'],
            'access_instructions' => ['nullable', 'string'],
            'rooftop_access' => ['nullable', 'boolean'],
            'power_availability' => ['nullable', 'string'],
            'building_type' => ['nullable', 'string'],
            'coverage_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->locations->update($location, $data, Auth::user()));
    }
}
