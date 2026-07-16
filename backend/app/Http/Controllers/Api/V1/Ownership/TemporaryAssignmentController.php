<?php

namespace App\Http\Controllers\Api\V1\Ownership;

use App\Http\Controllers\Controller;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\TemporaryCollectionAssignment;
use App\Services\Ownership\TemporaryAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemporaryAssignmentController extends Controller
{
    public function __construct(private TemporaryAssignmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = TemporaryCollectionAssignment::query()->with(['customer', 'temporaryCollector.user'])->orderByDesc('id');
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->boolean('active_only')) $q->currentlyActive();
        return ApiResponse::success($q->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'temporary_collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'string', 'max:16'],
        ]);
        $row = $this->service->create(
            Customer::withoutGlobalScopes()->findOrFail($data['customer_id']),
            Collector::query()->findOrFail($data['temporary_collector_id']),
            $request->user(),
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null,
            $data['priority'] ?? 'normal',
        );
        return ApiResponse::success($row, null, 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $row = TemporaryCollectionAssignment::query()->findOrFail($id);
        return ApiResponse::success($this->service->cancel($row, $request->user(), $data['reason'] ?? null));
    }
}
