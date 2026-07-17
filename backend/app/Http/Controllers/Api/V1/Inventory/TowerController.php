<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Tower;
use App\Services\Inventory\TowerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class TowerController extends Controller
{
    public function __construct(private TowerService $towers) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tower::class);
        $user = Auth::user();
        $q = Tower::query()->with('site:id,code,name');
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('branch_id'), fn ($qq) => $qq->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('site_id'), fn ($qq) => $qq->where('site_id', $request->integer('site_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tower::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'site_id' => ['required', 'integer', 'exists:inventory_sites,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'height_m' => ['nullable', 'numeric'],
            'structure_type' => ['nullable', 'string', 'max:64'],
            'owner' => ['nullable', 'string', 'max:255'],
            'operational_status' => ['nullable', 'string', 'max:32'],
            'installation_date' => ['nullable', 'date'],
            'last_inspection_date' => ['nullable', 'date'],
            'next_inspection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        try {
            return ApiResponse::success($this->towers->create($data, $request->user()), null, 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tower = Tower::query()->findOrFail($id);
        $this->authorize('update', $tower);
        $data = $request->validate([
            'height_m' => ['nullable', 'numeric'],
            'structure_type' => ['nullable', 'string', 'max:64'],
            'owner' => ['nullable', 'string', 'max:255'],
            'operational_status' => ['nullable', 'string', 'max:32'],
            'installation_date' => ['nullable', 'date'],
            'last_inspection_date' => ['nullable', 'date'],
            'next_inspection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->towers->update($tower, $data, $request->user()));
    }
}