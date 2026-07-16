<?php

namespace App\Http\Controllers\Api\V1\Ownership;

use App\Http\Controllers\Controller;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerCollectorOwnership;
use App\Models\CustomerCollectorOwnershipHistory;
use App\Models\CustomerWorkQueue;
use App\Models\OwnershipConflict;
use App\Services\Ownership\CustomerOwnershipService;
use App\Services\Ownership\OwnershipResolutionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOwnershipController extends Controller
{
    public function __construct(
        private CustomerOwnershipService $ownerships,
        private OwnershipResolutionService $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = CustomerCollectorOwnership::query()->with(['customer', 'collector.user', 'branch'])->orderByDesc('id');
        if ($request->filled('branch_id')) $q->where('branch_id', $request->integer('branch_id'));
        if ($request->filled('collector_id')) $q->where('collector_id', $request->integer('collector_id'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        else $q->where('is_active', true);
        return ApiResponse::success($q->paginate(min(100, max(1, $request->integer('per_page', 25)))));
    }

    public function unassigned(Request $request): JsonResponse
    {
        $q = CustomerWorkQueue::query()->with('customer')
            ->whereIn('ownership_source', ['unassigned', 'conflict'])
            ->orderByDesc('total_open_balance');
        if ($request->filled('branch_id')) $q->where('branch_id', $request->integer('branch_id'));
        return ApiResponse::success($q->paginate(25));
    }

    public function byCollector(int $collectorId): JsonResponse
    {
        $rows = CustomerCollectorOwnership::query()->active()->where('collector_id', $collectorId)
            ->with(['customer', 'branch'])->orderBy('id')->get();
        return ApiResponse::success($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'start_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'route_label' => ['nullable', 'string', 'max:120'],
        ]);
        $customer = Customer::withoutGlobalScopes()->findOrFail($data['customer_id']);
        $collector = Collector::query()->findOrFail($data['collector_id']);
        $row = $this->ownerships->assignPermanent($customer, $collector, $request->user(), $data['start_date'], $data['reason'] ?? null, $data['area'] ?? null, $data['route_label'] ?? null);
        return ApiResponse::success($row->load(["customer", "collector"]), null, 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'start_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $collector = Collector::query()->findOrFail($data['collector_id']);
        $result = $this->ownerships->bulkAssign($data['customer_ids'], $collector, $request->user(), $data['start_date'], $data['reason'] ?? null);
        return ApiResponse::success($result);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'to_collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $customer = Customer::withoutGlobalScopes()->findOrFail($data['customer_id']);
        $collector = Collector::query()->findOrFail($data['to_collector_id']);
        $row = $this->ownerships->transfer($customer, $collector, $request->user(), $data['effective_date'], $data['reason']);
        return ApiResponse::success($row->load(['customer', 'collector']));
    }

    public function end(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $ownership = CustomerCollectorOwnership::query()->findOrFail($id);
        return ApiResponse::success($this->ownerships->end($ownership, $request->user(), $data['reason']));
    }

    public function history(int $customerId): JsonResponse
    {
        $rows = CustomerCollectorOwnershipHistory::query()->where('customer_id', $customerId)->orderByDesc('id')->limit(100)->get();
        return ApiResponse::success($rows);
    }

    public function resolve(int $customerId): JsonResponse
    {
        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);
        return ApiResponse::success($this->resolver->resolve($customer));
    }

    public function conflicts(Request $request): JsonResponse
    {
        $q = OwnershipConflict::query()->orderByDesc('id');
        if ($request->string('status', 'open') !== 'all') $q->where('status', $request->string('status', 'open'));
        return ApiResponse::success($q->paginate(50));
    }

    public function resolveConflict(Request $request, int $id): JsonResponse
    {
        $row = OwnershipConflict::query()->findOrFail($id);
        $row->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        return ApiResponse::success($row);
    }
}
