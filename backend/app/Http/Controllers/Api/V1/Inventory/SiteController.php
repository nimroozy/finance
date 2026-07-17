<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Site;
use App\Services\Inventory\SiteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SiteController extends Controller
{
    public function __construct(private SiteService $sites) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Site::class);
        $user = Auth::user();
        $q = Site::query()->with('towers');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Site::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'site_type' => ['nullable', 'string', 'max:64'],
            'landlord' => ['nullable', 'string', 'max:255'],
            'lease_start' => ['nullable', 'date'],
            'lease_end' => ['nullable', 'date'],
            'rent' => ['nullable', 'numeric'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'power_source' => ['nullable', 'string', 'max:255'],
            'power_capacity' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->sites->create($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $site = Site::with('towers')->findOrFail($id);
        $this->authorize('view', $site);

        return ApiResponse::success($site);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $site = Site::query()->findOrFail($id);
        $this->authorize('update', $site);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'site_type' => ['nullable', 'string', 'max:64'],
            'landlord' => ['nullable', 'string', 'max:255'],
            'lease_start' => ['nullable', 'date'],
            'lease_end' => ['nullable', 'date'],
            'rent' => ['nullable', 'numeric'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'power_source' => ['nullable', 'string', 'max:255'],
            'power_capacity' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->sites->update($site, $data, $request->user()));
    }
}