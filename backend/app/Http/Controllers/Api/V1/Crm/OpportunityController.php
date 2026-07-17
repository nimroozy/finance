<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Services\Crm\OpportunityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OpportunityController extends Controller
{
    public function __construct(private OpportunityService $opportunities) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.pipeline.manage') || Auth::user()->can('crm.leads.view'), 403);

        $query = Opportunity::query()
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')))
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.pipeline.manage'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:64'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'value' => ['nullable', 'numeric'],
            'expected_close_date' => ['nullable', 'date'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);
        $opportunity = $this->opportunities->createFromLead($lead, $data, Auth::user());

        return ApiResponse::success($opportunity, null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.pipeline.manage'), 403);

        $opportunity = Opportunity::query()->findOrFail($id);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:64'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'value' => ['nullable', 'numeric'],
            'expected_close_date' => ['nullable', 'date'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'win_reason' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponse::success($this->opportunities->update($opportunity, $data, Auth::user()));
    }
}
