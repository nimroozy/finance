<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PromiseToPay;
use App\Services\Promises\PromiseToPayService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromiseController extends Controller
{
    public function __construct(protected PromiseToPayService $promises) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PromiseToPay::class);

        $page = PromiseToPay::query()
            ->with(['customer:id,contact_name,company_name,customer_number', 'collector.user:id,name', 'branch:id,code,name_en,name_fa'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PromiseToPay::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:customer_assignments,id'],
            'visit_id' => ['nullable', 'integer', 'exists:collection_visits,id'],
            'collector_id' => ['nullable', 'integer', 'exists:collectors,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'promised_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'allow_past_date' => ['nullable', 'boolean'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $data['branch_id'] = $customer->branch_id;

        return ApiResponse::success($this->promises->create($data, Auth::user()), null, 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $promise = PromiseToPay::findOrFail($id);
        $this->authorize('cancel', $promise);

        $data = $request->validate(['reason' => ['nullable', 'string']]);

        return ApiResponse::success($this->promises->cancel($promise, Auth::user(), $data['reason'] ?? null));
    }

    public function fulfill(int $id): JsonResponse
    {
        $promise = PromiseToPay::findOrFail($id);
        $this->authorize('manage', $promise);

        return ApiResponse::success($this->promises->fulfill($promise, Auth::user()));
    }

    public function supersede(Request $request, int $id): JsonResponse
    {
        $promise = PromiseToPay::findOrFail($id);
        $this->authorize('manage', $promise);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'promised_date' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'allow_past_date' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->promises->supersede($promise, $data, Auth::user()), null, 201);
    }
}
