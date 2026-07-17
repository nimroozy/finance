<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\CustomerEquipment;
use App\Models\Inventory\FixedAsset;
use App\Services\Inventory\CustomerEquipmentService;
use App\Services\Inventory\InventoryDashboardService;
use App\Services\Inventory\InventoryImportService;
use App\Services\Inventory\InventoryNumberService;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventorySearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryOpsController extends Controller
{
    public function __construct(
        private InventoryDashboardService $dashboard,
        private InventoryReportService $reports,
        private InventorySearchService $search,
        private InventoryImportService $import,
        private CustomerEquipmentService $customerEquipment,
        private InventoryNumberService $numbers,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FixedAsset::class);
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return ApiResponse::success($this->dashboard->summary($branchId));
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success($this->search->search($data['q'], $data['branch_id'] ?? null));
    }

    public function reportBalances(Request $request): StreamedResponse
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return $this->reports->stockBalancesCsv($branchId);
    }

    public function reportTransactions(Request $request): StreamedResponse
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return $this->reports->transactionsCsv($branchId);
    }

    public function importDryRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'rows' => ['required', 'array', 'min:1'],
        ]);

        return ApiResponse::success($this->import->dryRun($data['type'], $data['rows']));
    }

    public function importApply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'rows' => ['required', 'array', 'min:1'],
        ]);
        $result = $this->import->apply($data['type'], $data['rows'], $request->user());
        $status = $result['errors'] === [] ? 200 : 422;

        return ApiResponse::success($result, null, $status);
    }

    public function customerEquipmentIndex(Request $request): JsonResponse
    {
        $user = Auth::user();
        $q = CustomerEquipment::query()->with(['product:id,code,name_en', 'equipment']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $q->when($request->filled('customer_id'), fn ($qq) => $qq->where('customer_id', $request->integer('customer_id')));
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function fixedAssetsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FixedAsset::class);
        $user = Auth::user();
        $q = FixedAsset::query();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $q->whereIn('branch_id', $user->branchIds());
        }
        $page = $q->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function fixedAssetsStore(Request $request): JsonResponse
    {
        $this->authorize('create', FixedAsset::class);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
            'equipment_id' => ['nullable', 'integer', 'exists:inventory_equipment,id'],
            'category' => ['nullable', 'string', 'max:64'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'site_id' => ['nullable', 'integer', 'exists:inventory_sites,id'],
            'tower_id' => ['nullable', 'integer', 'exists:inventory_towers,id'],
            'custodian_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'acquisition_cost' => ['nullable', 'numeric'],
            'acquisition_date' => ['nullable', 'date'],
            'condition' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'warranty_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $asset = FixedAsset::query()->create(array_merge($data, [
            'asset_number' => $this->numbers->asset((int) $data['branch_id']),
        ]));

        return ApiResponse::success($asset, null, 201);
    }
}