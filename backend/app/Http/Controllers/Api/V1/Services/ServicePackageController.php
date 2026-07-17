<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Models\Services\ServicePackage;
use App\Services\ServiceLifecycle\ServicePackageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ServicePackageController extends Controller
{
    public function __construct(private ServicePackageService $packages) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.packages.view') || Auth::user()?->can('services.view'), 403);

        $query = ServicePackage::query()->with('versions');
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('branch_id')->orWhereIn('branch_id', $user->branchIds());
            });
        }
        $query->when($request->filled('branch_id'), function ($q) use ($request) {
            $q->where(function ($inner) use ($request) {
                $inner->whereNull('branch_id')->orWhere('branch_id', $request->integer('branch_id'));
            });
        })->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $page = $query->orderBy('code')->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.packages.manage'), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'service_type_code' => ['required', 'string', 'exists:service_types,code'],
            'access_technology_code' => ['required', 'string', 'exists:service_access_technologies,code'],
            'download_mbps' => ['nullable', 'integer'],
            'upload_mbps' => ['nullable', 'integer'],
            'monthly_price' => ['nullable', 'numeric'],
            'installation_fee' => ['nullable', 'numeric'],
            'billing_frequency' => ['nullable', 'string'],
            'contract_period_months' => ['nullable', 'integer'],
            'grace_period_days' => ['nullable', 'integer'],
            'equipment_template' => ['nullable', 'array'],
            'sla_template_id' => ['nullable', 'integer', 'exists:service_sla_templates,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        try {
            $package = $this->packages->create($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($package, null, 201);
    }

    public function storeVersion(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.packages.manage'), 403);
        $package = ServicePackage::query()->findOrFail($id);
        $data = $request->validate([
            'effective_date' => ['nullable', 'date'],
            'monthly_price' => ['required', 'numeric'],
            'installation_fee' => ['nullable', 'numeric'],
            'download_mbps' => ['nullable', 'integer'],
            'upload_mbps' => ['nullable', 'integer'],
            'sla_json' => ['nullable', 'array'],
            'terms' => ['nullable', 'string'],
            'equipment_requirements' => ['nullable', 'array'],
        ]);

        $version = $this->packages->createVersion($package, $data, Auth::user());

        return ApiResponse::success($version, null, 201);
    }
}
