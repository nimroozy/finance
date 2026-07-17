<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CoverageCheck;
use App\Models\Crm\Lead;
use App\Services\Crm\CoverageCheckService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CoverageCheckController extends Controller
{
    public function __construct(private CoverageCheckService $coverage) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.coverage.manage') || Auth::user()->can('crm.leads.view'), 403);

        $query = CoverageCheck::query()
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.coverage.manage'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'result' => ['required', 'string', Rule::in(CoverageCheck::RESULTS)],
            'candidate_tower' => ['nullable', 'string', 'max:255'],
            'distance_m' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'engineer_id' => ['nullable', 'integer', 'exists:users,id'],
            'signal_estimate' => ['nullable', 'string', 'max:255'],
            'checked_at' => ['nullable', 'date'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);

        try {
            $check = $this->coverage->record($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($check, null, 201);
    }
}
