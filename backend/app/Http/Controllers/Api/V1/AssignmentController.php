<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AssignmentBulkOperation;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Services\Assignments\AssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(protected AssignmentService $assignments) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerAssignment::class);

        $query = CustomerAssignment::query()
            ->with(['customer:id,contact_name,outstanding_receivable', 'collector.user:id,name', 'branch:id,code,name_en'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('collector_id'), fn ($q) => $q->where('collector_id', $request->integer('collector_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id');

        $page = $query->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $assignment = CustomerAssignment::with(['customer', 'collector.user', 'history', 'comments'])->findOrFail($id);
        $this->authorize('view', $assignment);

        return ApiResponse::success($assignment);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerAssignment::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'planned_visit_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'manager_notes' => ['nullable', 'string'],
            'collector_instructions' => ['nullable', 'string'],
            'assignment_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::withoutGlobalScopes()->findOrFail($data['customer_id']);
        $warnings = [];
        if ((float) $customer->outstanding_receivable <= 0) {
            $warnings[] = 'Customer outstanding balance is zero.';
        }

        $assignment = $this->assignments->create($data, Auth::user());

        return ApiResponse::success([
            'assignment' => $assignment,
            'warnings' => $warnings,
        ], null, 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerAssignment::class);

        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->assignments->bulk($data, Auth::user());

        return ApiResponse::success($result, null, 201);
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);
        $this->authorize('reassign', $assignment);

        $data = $request->validate([
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $new = $this->assignments->reassign($assignment, $data, Auth::user());

        return ApiResponse::success($new);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);
        $this->authorize('cancel', $assignment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return ApiResponse::success($this->assignments->cancel($assignment, $data, Auth::user()));
    }

    public function accept(int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);
        $this->authorize('accept', $assignment);

        return ApiResponse::success($this->assignments->accept($assignment, Auth::user()));
    }

    public function viewed(int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);
        $this->authorize('accept', $assignment);

        return ApiResponse::success($this->assignments->markViewed($assignment, Auth::user()));
    }

    public function history(int $id): JsonResponse
    {
        $assignment = CustomerAssignment::findOrFail($id);
        $this->authorize('view', $assignment);

        return ApiResponse::success($assignment->history()->with('changedBy:id,name')->orderByDesc('id')->get());
    }

    public function unassignedDebtors(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerAssignment::class);

        $query = Customer::query()
            ->where('status', Customer::STATUS_ACTIVE)
            ->where('is_unmapped', false)
            ->where('outstanding_receivable', '>', 0)
            ->whereDoesntHave('assignments', fn ($q) => $q->withoutGlobalScopes()->where('is_active', true))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('outstanding_receivable');

        $page = $query->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function collectorsWorkload(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerAssignment::class);

        return ApiResponse::success($this->assignments->workload($request->integer('branch_id') ?: null));
    }

    public function autoPreview(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerAssignment::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'strategy' => ['nullable', 'string', 'in:round_robin,least_loaded'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer'],
            'collector_ids' => ['nullable', 'array'],
            'collector_ids.*' => ['integer'],
        ]);

        $op = $this->assignments->autoPreview($data, Auth::user());

        return ApiResponse::success($op, null, 201);
    }

    public function autoConfirm(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerAssignment::class);

        $data = $request->validate([
            'operation_id' => ['required', 'integer', 'exists:assignment_bulk_operations,id'],
        ]);

        $op = AssignmentBulkOperation::withoutGlobalScopes()->findOrFail($data['operation_id']);
        $result = $this->assignments->autoConfirm($op, Auth::user());

        return ApiResponse::success($result);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('export', CustomerAssignment::class);

        $assignments = CustomerAssignment::query()
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('id')
            ->cursor();

        return $this->assignments->exportCsv($assignments);
    }
}
