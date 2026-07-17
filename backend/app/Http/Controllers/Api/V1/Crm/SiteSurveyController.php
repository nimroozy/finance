<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Crm\SiteSurvey;
use App\Services\Crm\SiteSurveyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class SiteSurveyController extends Controller
{
    public function __construct(private SiteSurveyService $surveys) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.surveys.view') || Auth::user()->can('crm.surveys.manage'), 403);

        $query = SiteSurvey::query()
            ->with('task:id,task_number,status')
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.surveys.manage'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'los_status' => ['nullable', 'string', 'max:64'],
            'signal_estimate' => ['nullable', 'string', 'max:255'],
            'mounting_location' => ['nullable', 'string', 'max:255'],
            'power_availability' => ['nullable', 'string', 'max:255'],
            'equipment_recommendation' => ['nullable', 'string'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'survey_date' => ['nullable', 'date'],
            'customer_approved' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);

        try {
            $survey = $this->surveys->create($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($survey, null, 201);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.surveys.manage'), 403);

        $survey = SiteSurvey::query()->findOrFail($id);
        $data = $request->validate([
            'los_status' => ['nullable', 'string', 'max:64'],
            'signal_estimate' => ['nullable', 'string', 'max:255'],
            'mounting_location' => ['nullable', 'string', 'max:255'],
            'power_availability' => ['nullable', 'string', 'max:255'],
            'equipment_recommendation' => ['nullable', 'string'],
            'customer_approved' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'survey_date' => ['nullable', 'date'],
        ]);

        return ApiResponse::success($this->surveys->complete($survey, $data, Auth::user()));
    }
}
