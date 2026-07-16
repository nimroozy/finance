<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchCustomerPrefix;
use App\Models\Customer;
use App\Models\CustomerBranchMappingConflict;
use App\Models\CustomerBranchMappingHistory;
use App\Services\Mapping\CustomerBranchResolutionService;
use App\Services\Mapping\CustomerNumberPrefixMatcher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerPrefixMappingController extends Controller
{
    public function __construct(
        private CustomerNumberPrefixMatcher $matcher,
        private CustomerBranchResolutionService $resolver,
    ) {}

    public function index(): JsonResponse
    {
        $rows = BranchCustomerPrefix::query()
            ->with(['branch:id,code,name_en,name_fa,receipt_prefix'])
            ->orderBy('priority')
            ->orderBy('normalized_prefix')
            ->get()
            ->map(function (BranchCustomerPrefix $row) {
                $matched = Customer::withoutGlobalScopes()
                    ->where(function ($q) use ($row) {
                        $q->where('branch_mapping_prefix', $row->normalized_prefix)
                            ->orWhere('customer_number', 'like', $row->normalized_prefix.'%');
                    })
                    ->count();

                return array_merge($row->toArray(), [
                    'matched_customers_estimate' => $matched,
                    'examples' => [
                        $row->normalized_prefix.'-001254',
                        $row->normalized_prefix.'001254',
                        strtolower($row->normalized_prefix).'-001254',
                        $row->normalized_prefix.' 001254',
                    ],
                ]);
            });

        return ApiResponse::success($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $normalized = BranchCustomerPrefix::normalizePrefix($data['prefix']);
        if (BranchCustomerPrefix::query()->where('normalized_prefix', $normalized)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['prefix' => 'This prefix already exists while active.']);
        }

        $row = BranchCustomerPrefix::query()->create([
            'branch_id' => $data['branch_id'],
            'prefix' => trim($data['prefix']),
            'normalized_prefix' => $normalized,
            'active' => $data['active'] ?? true,
            'priority' => $data['priority'] ?? 100,
            'description' => $data['description'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return ApiResponse::success($row->load('branch'), status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = BranchCustomerPrefix::query()->findOrFail($id);
        $data = $this->validated($request, $row->id);
        $normalized = BranchCustomerPrefix::normalizePrefix($data['prefix']);
        if (BranchCustomerPrefix::query()
            ->where('normalized_prefix', $normalized)
            ->where('active', true)
            ->where('id', '!=', $row->id)
            ->exists()) {
            throw ValidationException::withMessages(['prefix' => 'This prefix already exists while active.']);
        }

        $row->update([
            'branch_id' => $data['branch_id'],
            'prefix' => trim($data['prefix']),
            'normalized_prefix' => $normalized,
            'active' => $data['active'] ?? $row->active,
            'priority' => $data['priority'] ?? $row->priority,
            'description' => $data['description'] ?? $row->description,
            'updated_by' => Auth::id(),
        ]);

        return ApiResponse::success($row->fresh()->load('branch'));
    }

    public function disable(int $id): JsonResponse
    {
        $row = BranchCustomerPrefix::query()->findOrFail($id);
        $row->update(['active' => false, 'updated_by' => Auth::id()]);

        return ApiResponse::success($row->fresh());
    }

    public function testNumber(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_number' => ['nullable', 'string', 'max:100']]);
        $hit = $this->matcher->detect($data['customer_number'] ?? null);
        $branch = $hit && ! ($hit['ambiguous'] ?? false) && ! empty($hit['branch_id'])
            ? Branch::withoutGlobalScopes()->find($hit['branch_id'])
            : null;

        return ApiResponse::success([
            'normalized' => $this->matcher->normalizeCustomerNumber($data['customer_number'] ?? null),
            'match' => $hit,
            'branch' => $branch?->only(['id', 'code', 'name_en', 'name_fa']),
            'unknown_prefix' => $this->matcher->hasUnknownAlphaPrefix($data['customer_number'] ?? null),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:32'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = $data['limit'] ?? 25;
        $q = Customer::withoutGlobalScopes()->orderByDesc('id');
        if (! empty($data['prefix'])) {
            $p = strtoupper(trim($data['prefix']));
            $q->where('customer_number', 'like', $p.'%');
        }
        $customers = $q->limit($limit)->get();
        $out = [];
        foreach ($customers as $customer) {
            $resolution = $this->resolver->resolve($customer);
            $out[] = [
                'customer_id' => $customer->id,
                'customer_number' => $customer->customer_number,
                'contact_name' => $customer->contact_name,
                'current_branch_id' => $customer->branch_id,
                'resolution' => $resolution,
            ];
        }

        return ApiResponse::success($out);
    }

    public function dryRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'prefix' => ['nullable', 'string'],
        ]);
        $params = ['--no-interaction' => true];
        if (! empty($data['branch_id'])) {
            $params['--branch'] = $data['branch_id'];
        }
        if (! empty($data['prefix'])) {
            $params['--prefix'] = $data['prefix'];
        }
        Artisan::call('customers:map-branches-by-prefix', $params);

        return ApiResponse::success([
            'output' => Artisan::output(),
            'mode' => 'dry-run',
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'prefix' => ['nullable', 'string'],
            'confirm' => ['required', 'accepted'],
        ]);
        $params = ['--apply' => true, '--no-interaction' => true];
        if (! empty($data['branch_id'])) {
            $params['--branch'] = $data['branch_id'];
        }
        if (! empty($data['prefix'])) {
            $params['--prefix'] = $data['prefix'];
        }
        Artisan::call('customers:map-branches-by-prefix', $params);

        return ApiResponse::success([
            'output' => Artisan::output(),
            'mode' => 'apply',
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $rows = CustomerBranchMappingConflict::query()
            ->with(['customer:id,customer_number,contact_name,branch_id,is_unmapped', 'prefixBranch:id,code,name_en', 'locationBranch:id,code,name_en'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($rows->items(), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $rows = CustomerBranchMappingHistory::query()
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($rows->items(), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    public function branchMetrics(int $branchId): JsonResponse
    {
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        $prefixes = BranchCustomerPrefix::query()->where('branch_id', $branchId)->orderBy('priority')->get();
        $matched = Customer::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('branch_mapping_source', CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX)
            ->count();
        $conflicts = CustomerBranchMappingConflict::query()
            ->where('status', 'open')
            ->where(function ($q) use ($branchId) {
                $q->where('prefix_branch_id', $branchId)->orWhere('location_branch_id', $branchId)->orWhere('existing_branch_id', $branchId);
            })->count();
        $unmapped = Customer::withoutGlobalScopes()->where('is_unmapped', true)->count();
        $lastRun = CustomerBranchMappingHistory::query()
            ->where(function ($q) use ($branchId) {
                $q->where('to_branch_id', $branchId)->orWhere('from_branch_id', $branchId);
            })
            ->orderByDesc('id')
            ->value('created_at');

        return ApiResponse::success([
            'branch' => $branch->only(['id', 'code', 'name_en', 'name_fa']),
            'prefixes' => $prefixes,
            'matched_by_prefix' => $matched,
            'open_conflicts' => $conflicts,
            'unmapped_customers' => $unmapped,
            'last_prefix_mapping_run' => $lastRun,
        ]);
    }

    public function reportSummary(): JsonResponse
    {
        $byBranch = Branch::withoutGlobalScopes()->where('is_active', true)->get()->map(function (Branch $b) {
            $customers = Customer::withoutGlobalScopes()->where('branch_id', $b->id);
            $debt = (clone $customers)->sum('outstanding_receivable');

            return [
                'branch_id' => $b->id,
                'code' => $b->code,
                'name_en' => $b->name_en,
                'prefix_customers' => Customer::withoutGlobalScopes()
                    ->where('branch_id', $b->id)
                    ->where('branch_mapping_source', CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX)
                    ->count(),
                'total_customers' => (clone $customers)->count(),
                'total_debt' => $debt,
            ];
        });

        return ApiResponse::success([
            'by_branch' => $byBranch,
            'unknown_prefixes' => CustomerBranchMappingConflict::query()->where('conflict_type', 'unknown_prefix')->where('status', 'open')->count(),
            'missing_customer_numbers' => Customer::withoutGlobalScopes()
                ->where(function ($q) {
                    $q->whereNull('customer_number')->orWhere('customer_number', '');
                })
                ->count(),
            'admin_overrides' => Customer::withoutGlobalScopes()->where('administrator_branch_override', true)->count(),
            'protected_history_pending' => CustomerBranchMappingHistory::query()->where('protected_history', true)->where('applied', false)->count(),
            'open_conflicts' => CustomerBranchMappingConflict::query()->where('status', 'open')->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'prefix' => ['required', 'string', 'max:32'],
            'active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
