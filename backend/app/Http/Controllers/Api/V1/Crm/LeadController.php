<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Services\Crm\CustomerConversionService;
use App\Services\Crm\LeadPipelineService;
use App\Services\Crm\LeadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class LeadController extends Controller
{
    public function __construct(
        private LeadService $leads,
        private LeadPipelineService $pipeline,
        private CustomerConversionService $conversion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        $user = Auth::user();

        $query = Lead::query()->with(['salesperson:id,name', 'owner:id,name', 'source']);

        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $query
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('pipeline_stage'), fn ($q) => $q->where('pipeline_stage', $request->string('pipeline_stage')))
            ->when($request->filled('status'), fn ($q) => $q->where('pipeline_stage', $request->string('status')))
            ->when($request->filled('salesperson_id'), fn ($q) => $q->where('salesperson_id', $request->integer('salesperson_id')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('source_code'), fn ($q) => $q->where('source_code', $request->string('source_code')))
            ->when($request->filled('phone'), fn ($q) => $q->where('phone', 'like', '%'.$request->string('phone').'%'));

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('lead_number', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page->through(fn (Lead $l) => $this->serialize($l)));
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with([
            'salesperson:id,name', 'owner:id,name', 'source', 'activities' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'followUps' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'quotations' => fn ($q) => $q->orderByDesc('id')->limit(10),
            'stageTransitions' => fn ($q) => $q->orderByDesc('id')->limit(20),
        ])->findOrFail($id);
        $this->authorize('view', $lead);

        $payload = $this->serialize($lead);
        $payload['allowed_transitions'] = Lead::allowedTransitions()[$lead->pipeline_stage] ?? [];
        $payload['activities'] = $lead->activities;
        $payload['follow_ups'] = $lead->followUps;
        $payload['quotations'] = $lead->quotations;
        $payload['stage_transitions'] = $lead->stageTransitions;

        return ApiResponse::success($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'organization_id' => ['nullable', 'integer', 'exists:crm_organizations,id'],
            'contact_id' => ['nullable', 'integer', 'exists:crm_contacts,id'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'source_code' => ['nullable', 'string', 'exists:crm_lead_sources,code'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'referral' => ['nullable', 'string', 'max:255'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_value' => ['nullable', 'numeric'],
            'estimated_mrr' => ['nullable', 'numeric'],
            'expected_installation_fee' => ['nullable', 'numeric'],
            'interested_package' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['nullable', 'string', Rule::in(Lead::CUSTOMER_TYPES)],
            'pipeline_stage' => ['nullable', 'string', Rule::in(Lead::PIPELINE_STAGES)],
            'status' => ['nullable', 'string', Rule::in(Lead::PIPELINE_STAGES)],
            'next_follow_up_at' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'competitor' => ['nullable', 'string', 'max:255'],
            'opportunity_value' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->leads->create($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            ...$this->serialize($result['lead']),
            'duplicate_warning' => $result['duplicate_warning'],
        ], null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('update', $lead);

        $data = $request->validate([
            'company' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric'],
            'gps_lng' => ['nullable', 'numeric'],
            'source_code' => ['nullable', 'string', 'exists:crm_lead_sources,code'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'referral' => ['nullable', 'string', 'max:255'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_value' => ['nullable', 'numeric'],
            'estimated_mrr' => ['nullable', 'numeric'],
            'expected_installation_fee' => ['nullable', 'numeric'],
            'interested_package' => ['nullable', 'string', 'max:255'],
            'customer_type' => ['nullable', 'string', Rule::in(Lead::CUSTOMER_TYPES)],
            'next_follow_up_at' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'competitor' => ['nullable', 'string', 'max:255'],
            'opportunity_value' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $lead = $this->leads->update($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($lead));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('assign', $lead);

        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $lead = $this->leads->assign($lead, (int) $data['owner_id'], Auth::user(), $data['salesperson_id'] ?? null);

        return ApiResponse::success($this->serialize($lead));
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('update', $lead);

        $data = $request->validate([
            'pipeline_stage' => ['nullable', 'string', Rule::in(Lead::PIPELINE_STAGES)],
            'status' => ['nullable', 'string', Rule::in(Lead::PIPELINE_STAGES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $to = $data['pipeline_stage'] ?? $data['status'] ?? null;
        if (! $to) {
            return ApiResponse::error('pipeline_stage is required.', [], 422);
        }

        try {
            $lead = $this->pipeline->transition($lead, $to, Auth::user(), $data['reason'] ?? null, $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($lead));
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('convert', $lead);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'collector_id' => ['nullable', 'integer', 'exists:collectors,id'],
            'win_reason' => ['nullable', 'string', 'max:255'],
            'expand_template' => ['nullable', 'boolean'],
            'template_code' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->conversion->convert($lead, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'lead' => $this->serialize($result['lead']),
            'customer_id' => $result['customer']->id,
            'installation_id' => $result['installation_id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'branch_id' => $lead->branch_id,
            'organization_id' => $lead->organization_id,
            'contact_id' => $lead->contact_id,
            'company' => $lead->company,
            'contact_person' => $lead->contact_person,
            'phone' => $lead->phone,
            'whatsapp' => $lead->whatsapp,
            'email' => $lead->email,
            'address' => $lead->address,
            'gps_lat' => $lead->gps_lat,
            'gps_lng' => $lead->gps_lng,
            'source_code' => $lead->source_code,
            'campaign' => $lead->campaign,
            'referral' => $lead->referral,
            'salesperson_id' => $lead->salesperson_id,
            'salesperson' => $lead->relationLoaded('salesperson') ? $lead->salesperson : null,
            'lead_value' => $lead->lead_value,
            'estimated_mrr' => $lead->estimated_mrr,
            'expected_installation_fee' => $lead->expected_installation_fee,
            'interested_package' => $lead->interested_package,
            'customer_type' => $lead->customer_type,
            'pipeline_stage' => $lead->pipeline_stage,
            'status' => $lead->pipeline_stage,
            'owner_id' => $lead->owner_id,
            'owner' => $lead->relationLoaded('owner') ? $lead->owner : null,
            'next_follow_up_at' => optional($lead->next_follow_up_at)?->toIso8601String(),
            'probability' => $lead->probability,
            'expected_close_date' => optional($lead->expected_close_date)?->toDateString(),
            'competitor' => $lead->competitor,
            'lost_reason' => $lead->lost_reason,
            'win_reason' => $lead->win_reason,
            'converted_customer_id' => $lead->converted_customer_id,
            'installation_id' => $lead->installation_id,
            'opportunity_value' => $lead->opportunity_value,
            'notes' => $lead->notes,
            'created_by' => $lead->created_by,
            'created_at' => optional($lead->created_at)?->toIso8601String(),
            'updated_at' => optional($lead->updated_at)?->toIso8601String(),
        ];
    }
}
