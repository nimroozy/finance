<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionVisit;
use App\Services\Visits\VisitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    public function __construct(protected VisitService $visits) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CollectionVisit::class);

        $page = CollectionVisit::query()
            ->with(['customer:id,contact_name', 'collector.user:id,name'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('collector_id'), fn ($q) => $q->where('collector_id', $request->integer('collector_id')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->string('outcome')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('visited_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $visit = CollectionVisit::with(['customer', 'collector.user', 'files', 'promise'])->findOrFail($id);
        $this->authorize('view', $visit);

        return ApiResponse::success($visit);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CollectionVisit::class);

        $data = $request->validate([
            'customer_id' => ['required_without:assignment_id', 'integer', 'exists:customers,id'],
            'assignment_id' => ['nullable', 'integer', 'exists:customer_assignments,id'],
            'collector_id' => ['nullable', 'integer', 'exists:collectors,id'],
            'route_stop_id' => ['nullable', 'integer', 'exists:collection_route_stops,id'],
            'visited_at' => ['nullable', 'date'],
            'outcome' => ['required', 'string', Rule::in(CollectionVisit::outcomeCodes())],
            'notes' => ['nullable', 'string'],
            'contact_status' => ['nullable', 'string', 'max:100'],
            'person_met' => ['nullable', 'string', 'max:255'],
            'follow_up_required' => ['nullable', 'boolean'],
            'follow_up_date' => ['nullable', 'date'],
            'escalation_flag' => ['nullable', 'boolean'],
            'origin' => ['nullable', 'string', 'in:online,offline'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'gps_permission_state' => ['nullable', 'string', 'in:granted,denied,unavailable,prompt'],
            'gps_source' => ['nullable', 'string', 'max:50'],
            'gps_captured_at' => ['nullable', 'date'],
            'device_info' => ['nullable', 'string', 'max:500'],
            'promise' => ['nullable', 'array'],
            'promise.amount' => ['required_with:promise', 'numeric', 'gt:0'],
            'promise.promised_date' => ['required_with:promise', 'date'],
            'promise.currency' => ['nullable', 'string', 'size:3'],
            'promise.notes' => ['nullable', 'string'],
            'promise.allow_past_date' => ['nullable', 'boolean'],
        ]);

        $visit = $this->visits->create($data, Auth::user());

        return ApiResponse::success($visit, null, 201);
    }

    public function correctionNote(Request $request, int $id): JsonResponse
    {
        $visit = CollectionVisit::findOrFail($id);
        $this->authorize('correct', $visit);

        $data = $request->validate([
            'correction_note' => ['required', 'string', 'max:5000'],
        ]);

        return ApiResponse::success($this->visits->addCorrectionNote($visit, $data['correction_note'], Auth::user()));
    }

    public function outcomes(): JsonResponse
    {
        return ApiResponse::success(array_values(CollectionVisit::OUTCOMES));
    }
}
