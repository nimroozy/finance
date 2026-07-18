<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InstallationResource;
use App\Models\Tickets\Installation;
use App\Services\Installations\InstallationQueueService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InstallationController extends Controller
{
    public function __construct(private InstallationQueueService $installations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Installation::class);
        $user = Auth::user();

        $query = Installation::query();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $page = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            $page->through(fn (Installation $i) => (new InstallationResource($i))->resolve())
        );
    }

    public function show(int $id): JsonResponse
    {
        $installation = Installation::with('tasks')->findOrFail($id);
        $this->authorize('view', $installation);

        return ApiResponse::success(new InstallationResource($installation));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Installation::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'prospect_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'requested_package' => ['nullable', 'string'],
            'requested_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'template_code' => ['nullable', 'string'],
            'expand_template' => ['nullable', 'boolean'],
        ]);

        $installation = $this->installations->create($data, Auth::user());

        return ApiResponse::success(new InstallationResource($installation), null, 201);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $installation = Installation::query()->findOrFail($id);
        $this->authorize('update', $installation);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(Installation::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $installation = $this->installations->transitionStatus(
                $installation,
                $data['status'],
                Auth::user(),
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new InstallationResource($installation));
    }
}
