<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceContract;
use App\Models\Services\ServiceFinanceHold;
use App\Models\Services\ServiceRelocation;
use App\Models\Tickets\Installation;
use App\Services\ServiceLifecycle\InstallationToServiceConverter;
use App\Services\ServiceLifecycle\NocServiceWorkspaceService;
use App\Services\ServiceLifecycle\ServiceContractService;
use App\Services\ServiceLifecycle\ServiceDashboardService;
use App\Services\ServiceLifecycle\ServiceMigrationTool;
use App\Services\ServiceLifecycle\ServiceReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ServiceOpsController extends Controller
{
    public function __construct(
        private ServiceDashboardService $dashboard,
        private NocServiceWorkspaceService $noc,
        private ServiceReportService $reports,
        private ServiceMigrationTool $migration,
        private InstallationToServiceConverter $converter,
        private ServiceContractService $contracts,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.dashboard.view') || Auth::user()?->can('services.view'), 403);

        return ApiResponse::success($this->dashboard->summary(Auth::user(), $request->integer('branch_id') ?: null));
    }

    public function noc(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.noc.view') || Auth::user()?->can('services.view'), 403);

        return ApiResponse::success($this->noc->workspace(Auth::user(), $request->integer('branch_id') ?: null));
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.reports.view') || Auth::user()?->can('services.view'), 403);

        return ApiResponse::success($this->reports->statusReport(Auth::user(), $request->integer('branch_id') ?: null));
    }

    public function migrationDryRun(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.migrate'), 403);

        return ApiResponse::success($this->migration->dryRun($request->integer('branch_id') ?: null));
    }

    public function migrationApply(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.migrate'), 403);

        return ApiResponse::success($this->migration->apply($request->integer('branch_id') ?: null, Auth::user()));
    }

    public function convertInstallation(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.create'), 403);
        $installation = Installation::query()->findOrFail($id);
        $data = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:service_packages,id'],
            'service_location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'allow_without_package' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service = $this->converter->convert($installation, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($service, null, 201);
    }

    public function changeRequests(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.change') || Auth::user()?->can('services.view'), 403);

        $query = ServiceChangeRequest::query()
            ->with(['service:id,service_number,customer_id,branch_id,commercial_status', 'requestedBy:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->whereHas('service', fn ($sq) => $sq->where('branch_id', $request->integer('branch_id')));
            })
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 25)));
    }

    public function relocations(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.relocate') || Auth::user()?->can('services.view'), 403);

        $query = ServiceRelocation::query()
            ->with(['service:id,service_number,customer_id,branch_id', 'oldLocation:id,name', 'newLocation:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->whereHas('service', fn ($sq) => $sq->where('branch_id', $request->integer('branch_id')));
            })
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 25)));
    }

    public function financeHolds(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->can('services.finance_holds.manage') || Auth::user()?->can('services.view'),
            403
        );

        $query = ServiceFinanceHold::query()
            ->with(['service:id,service_number,customer_id,branch_id,billing_status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->whereHas('service', fn ($sq) => $sq->where('branch_id', $request->integer('branch_id')));
            })
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 25)));
    }

    public function cancellations(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.cancel') || Auth::user()?->can('services.view'), 403);

        $query = ServiceCancellation::query()
            ->with(['service:id,service_number,customer_id,branch_id,commercial_status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $q->whereHas('service', fn ($sq) => $sq->where('branch_id', $request->integer('branch_id')));
            })
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 25)));
    }

    public function showContract(int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.contracts.view') || Auth::user()?->can('services.view'), 403);
        $contract = ServiceContract::query()
            ->with(['customer:id,contact_name,customer_number', 'service:id,service_number'])
            ->findOrFail($id);

        return ApiResponse::success($contract);
    }

    public function storeContract(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.contracts.manage'), 403);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'renewal_type' => ['nullable', 'string'],
            'setup_fee' => ['nullable', 'numeric'],
            'recurring_fee' => ['nullable', 'numeric'],
            'signed_date' => ['nullable', 'date'],
            'file_path' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'zoho_reference' => ['nullable', 'string'],
        ]);

        try {
            $contract = $this->contracts->create($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($contract, null, 201);
    }

    public function updateContract(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.contracts.manage'), 403);
        $contract = ServiceContract::query()->findOrFail($id);
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'renewal_type' => ['nullable', 'string'],
            'setup_fee' => ['nullable', 'numeric'],
            'recurring_fee' => ['nullable', 'numeric'],
            'signed_date' => ['nullable', 'date'],
            'file_path' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'zoho_reference' => ['nullable', 'string'],
        ]);

        try {
            $contract = $this->contracts->update($contract, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($contract);
    }

    public function contracts(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.contracts.view') || Auth::user()?->can('services.view'), 403);
        $query = ServiceContract::query()
            ->with(['customer:id,contact_name,customer_number', 'service:id,service_number'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        return ApiResponse::paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 25)));
    }
}
