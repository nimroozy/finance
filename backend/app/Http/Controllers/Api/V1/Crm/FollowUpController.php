<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Services\Crm\FollowUpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class FollowUpController extends Controller
{
    public function __construct(private FollowUpService $followUps) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.follow_ups.manage') || Auth::user()->can('crm.leads.view'), 403);

        $query = FollowUp::query()
            ->with('lead:id,lead_number,branch_id,contact_person')
            ->when($request->filled('lead_id'), fn ($q) => $q->where('lead_id', $request->integer('lead_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->orderBy('due_at');

        return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.follow_ups.manage'), 403);

        $data = $request->validate([
            'lead_id' => ['required', 'integer', 'exists:crm_leads,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $lead = Lead::query()->findOrFail($data['lead_id']);

        try {
            $followUp = $this->followUps->schedule($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($followUp, null, 201);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.follow_ups.manage'), 403);

        $followUp = FollowUp::query()->findOrFail($id);
        $data = $request->validate(['notes' => ['nullable', 'string']]);

        return ApiResponse::success($this->followUps->complete($followUp, Auth::user(), $data['notes'] ?? null));
    }

    public function cancel(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.follow_ups.manage'), 403);

        $followUp = FollowUp::query()->findOrFail($id);

        return ApiResponse::success($this->followUps->cancel($followUp, Auth::user()));
    }
}
