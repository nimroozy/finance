<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Services\Crm\ActivityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $activities) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.activities.manage') || Auth::user()->can('crm.leads.view'), 403);

        $query = Activity::query()
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.activities.manage'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:crm_opportunities,id'],
            'type' => ['required', 'string', Rule::in(Activity::TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'performed_at' => ['nullable', 'date'],
            'customer_visible' => ['nullable', 'boolean'],
            'next_follow_up_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);

        try {
            $activity = $this->activities->create($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($activity, null, 201);
    }
}
