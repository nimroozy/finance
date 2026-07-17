<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Services\Service;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceFinanceHold;
use App\Models\Services\ServiceRelocation;
use App\Services\ServiceLifecycle\ServiceActivationService;
use App\Services\ServiceLifecycle\ServiceBillingViewService;
use App\Services\ServiceLifecycle\ServiceCancellationService;
use App\Services\ServiceLifecycle\ServiceChangeRequestService;
use App\Services\ServiceLifecycle\ServiceFinanceHoldService;
use App\Services\ServiceLifecycle\ServiceLifecycleService;
use App\Services\ServiceLifecycle\ServiceReactivationService;
use App\Services\ServiceLifecycle\ServiceRelocationService;
use App\Services\ServiceLifecycle\ServiceRenewalService;
use App\Services\ServiceLifecycle\ServiceSearchService;
use App\Services\ServiceLifecycle\ServiceService;
use App\Services\ServiceLifecycle\ServiceSuspensionService;
use App\Services\ServiceLifecycle\ServiceTimelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceService $services,
        private ServiceSearchService $search,
        private ServiceLifecycleService $lifecycle,
        private ServiceActivationService $activations,
        private ServiceSuspensionService $suspensions,
        private ServiceReactivationService $reactivations,
        private ServiceCancellationService $cancellations,
        private ServiceChangeRequestService $changes,
        private ServiceRelocationService $relocations,
        private ServiceRenewalService $renewals,
        private ServiceFinanceHoldService $holds,
        private ServiceBillingViewService $billing,
        private ServiceTimelineService $timeline,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);
        $page = $this->search->search($request->all(), Auth::user(), $request->integer('per_page', 15));

        return ApiResponse::paginated($page->through(fn (Service $s) => $this->serialize($s)));
    }

    public function show(int $id): JsonResponse
    {
        $service = Service::with([
            'package', 'packageVersion', 'location', 'customer', 'slaTemplate',
            'installation', 'technicalOwner', 'salesOwner', 'supportOwner',
        ])->findOrFail($id);
        $this->authorize('view', $service);
        $payload = $this->serialize($service);
        $payload['allowed_commercial_transitions'] = Service::allowedCommercialTransitions()[$service->commercial_status] ?? [];
        $payload['allowed_operational_transitions'] = Service::allowedOperationalTransitions()[$service->operational_status] ?? [];
        $payload['activation_checklist'] = $this->activations->evaluateChecklist($service);

        return ApiResponse::success($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Service::class);
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'service_location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'package_id' => ['nullable', 'integer', 'exists:service_packages,id'],
            'package_version_id' => ['nullable', 'integer', 'exists:service_package_versions,id'],
            'service_type_code' => ['nullable', 'string', 'exists:service_types,code'],
            'access_technology_code' => ['nullable', 'string', 'exists:service_access_technologies,code'],
            'mrr' => ['nullable', 'numeric'],
            'installation_fee' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $service = $this->services->create($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('update', $service);
        $data = $request->validate([
            'service_location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'technical_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'support_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'tower_id' => ['nullable', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'network_node' => ['nullable', 'string', 'max:255'],
            'vlan' => ['nullable', 'string', 'max:64'],
            'static_ip' => ['nullable', 'string', 'max:64'],
            'public_ip' => ['nullable', 'string', 'max:64'],
            'private_ip' => ['nullable', 'string', 'max:64'],
            'pppoe_username_placeholder' => ['nullable', 'string', 'max:255'],
            'circuit_id' => ['nullable', 'string', 'max:255'],
            'customer_reference' => ['nullable', 'string', 'max:255'],
            'sla_template_id' => ['nullable', 'integer', 'exists:service_sla_templates,id'],
            'expiration_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
        ]);

        try {
            $service = $this->services->update($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('update', $service);
        $data = $request->validate([
            'commercial' => ['nullable', 'string', Rule::in(Service::COMMERCIAL_STATUSES)],
            'operational' => ['nullable', 'string', Rule::in(Service::OPERATIONAL_STATUSES)],
            'billing' => ['nullable', 'string', Rule::in(Service::BILLING_STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
        ]);

        try {
            $service = $this->lifecycle->transition($service, $data, Auth::user(), $data['reason'] ?? null, 'api', $data['comment'] ?? null);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function activationChecklist(int $id): JsonResponse
    {
        $service = Service::with(['customer', 'installation'])->findOrFail($id);
        $this->authorize('view', $service);

        return ApiResponse::success($this->activations->evaluateChecklist($service));
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $service = Service::with(['customer', 'installation'])->findOrFail($id);
        $this->authorize('activate', $service);
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
            'checklist' => ['nullable', 'array'],
            'skip_checklist' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $service = $this->activations->activate($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function confirmOnline(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('confirmOnline', $service);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service = $this->activations->confirmOnline($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('suspend', $service);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'expected_reactivation_at' => ['nullable', 'date'],
            'finance_reference' => ['nullable', 'string'],
            'ticket_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
        ]);

        try {
            $service = $this->suspensions->suspend($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('activate', $service);
        $data = $request->validate([
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $service = $this->reactivations->reactivate($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('cancel', $service);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'equipment_return_required' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable', 'boolean'],
        ]);

        try {
            $cancellation = $this->cancellations->request($service, $data, Auth::user());
            // complete defaults FALSE — no instant completion shortcut
            if ($request->boolean('complete', false)) {
                $cancellation = $this->cancellations->approve($cancellation, Auth::user());
                if ($cancellation->equipment_return_required) {
                    $cancellation = $this->cancellations->markEquipmentRecovered($cancellation, Auth::user());
                }
                $service = $this->cancellations->complete($cancellation, Auth::user());

                return ApiResponse::success($this->serialize($service));
            }
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($cancellation, null, 201);
    }

    public function approveCancellation(int $cancellationId): JsonResponse
    {
        $cancellation = ServiceCancellation::query()->findOrFail($cancellationId);
        $service = Service::query()->findOrFail($cancellation->service_id);
        $this->authorize('cancel', $service);

        try {
            $cancellation = $this->cancellations->approve($cancellation, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($cancellation);
    }

    public function recoverCancellationEquipment(Request $request, int $cancellationId): JsonResponse
    {
        $cancellation = ServiceCancellation::query()->findOrFail($cancellationId);
        $service = Service::query()->findOrFail($cancellation->service_id);
        $this->authorize('cancel', $service);
        $data = $request->validate(['notes' => ['nullable', 'string']]);

        try {
            $cancellation = $this->cancellations->markEquipmentRecovered($cancellation, Auth::user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($cancellation);
    }

    public function completeCancellation(int $cancellationId): JsonResponse
    {
        $cancellation = ServiceCancellation::query()->findOrFail($cancellationId);
        $service = Service::query()->findOrFail($cancellation->service_id);
        $this->authorize('cancel', $service);

        try {
            $service = $this->cancellations->complete($cancellation, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function changeRequest(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('change', $service);
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:64'],
            'requested_values' => ['required', 'array'],
            'requested_values.package_id' => ['nullable', 'integer', 'exists:service_packages,id'],
            'requested_values.package_version_id' => ['nullable', 'integer', 'exists:service_package_versions,id'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'apply' => ['nullable', 'boolean'],
            'approve' => ['nullable', 'boolean'],
        ]);

        $cr = $this->changes->request($service, $data, Auth::user());

        // apply/approve default FALSE — cannot self-approve by omitting boolean
        if ($request->boolean('approve', false) || $request->boolean('apply', false)) {
            try {
                $cr = $this->changes->approve($cr, Auth::user(), $request->boolean('apply', false));
            } catch (InvalidArgumentException $e) {
                return ApiResponse::error($e->getMessage(), [], 422);
            }
        }

        return ApiResponse::success($cr, null, 201);
    }

    public function advanceChangeRequest(Request $request, int $changeRequestId): JsonResponse
    {
        $cr = ServiceChangeRequest::query()->findOrFail($changeRequestId);
        $service = Service::query()->findOrFail($cr->service_id);
        $this->authorize('change', $service);
        $data = $request->validate([
            'step' => ['required', 'string', Rule::in(['technical', 'finance', 'approve', 'apply', 'close'])],
            'approved' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $approved = $request->boolean('approved', true);
            $cr = match ($data['step']) {
                'technical' => $this->changes->submitTechnicalReview($cr, Auth::user(), $approved, $data['notes'] ?? null),
                'finance' => $this->changes->submitFinanceReview($cr, Auth::user(), $approved, $data['notes'] ?? null),
                'approve' => $this->changes->approve($cr, Auth::user(), false),
                'apply' => $this->changes->apply($cr, Auth::user()),
                'close' => $this->changes->close($cr, Auth::user()),
            };
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($cr);
    }

    public function relocate(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('relocate', $service);
        $data = $request->validate([
            'new_location_id' => ['required', 'integer', 'exists:service_locations,id'],
            'new_tower_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable', 'boolean'],
        ]);

        try {
            $relocation = $this->relocations->request($service, $data, Auth::user());
            // complete defaults FALSE
            if ($request->boolean('complete', false)) {
                $service = $this->relocations->complete($relocation, Auth::user());

                return ApiResponse::success($this->serialize($service));
            }
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($relocation, null, 201);
    }

    public function startRelocation(int $relocationId): JsonResponse
    {
        $relocation = ServiceRelocation::query()->findOrFail($relocationId);
        $service = Service::query()->findOrFail($relocation->service_id);
        $this->authorize('relocate', $service);

        try {
            $relocation = $this->relocations->start($relocation, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($relocation);
    }

    public function completeRelocation(int $relocationId): JsonResponse
    {
        $relocation = ServiceRelocation::query()->findOrFail($relocationId);
        $service = Service::query()->findOrFail($relocation->service_id);
        $this->authorize('relocate', $service);

        try {
            $service = $this->relocations->complete($relocation, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->serialize($service));
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('renew', $service);
        $data = $request->validate([
            'term_months' => ['nullable', 'integer', 'min:1'],
            'new_expiration' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric'],
            'zoho_invoice_ref' => ['nullable', 'string'],
            'payment_ref' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $renewal = $this->renewals->renew($service, $data, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($renewal, null, 201);
    }

    public function placeHold(Request $request, int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('manageFinanceHold', $service);
        $data = $request->validate([
            'reason' => ['required', 'string'],
            'hold_type' => ['nullable', 'string'],
            'zoho_invoice_ref' => ['nullable', 'string'],
            'outstanding_snapshot' => ['nullable', 'array'],
            'review_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $hold = $this->holds->place($service, $data, Auth::user());

        return ApiResponse::success($hold, null, 201);
    }

    public function releaseHold(int $holdId): JsonResponse
    {
        $hold = ServiceFinanceHold::query()->findOrFail($holdId);
        $service = Service::query()->findOrFail($hold->service_id);
        $this->authorize('manageFinanceHold', $service);
        $hold = $this->holds->release($hold, Auth::user());

        return ApiResponse::success($hold);
    }

    public function billing(int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('view', $service);

        return ApiResponse::success($this->billing->forService($service));
    }

    public function timeline(int $id): JsonResponse
    {
        $service = Service::query()->findOrFail($id);
        $this->authorize('view', $service);

        return ApiResponse::success($this->timeline->forService($service));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Service $service): array
    {
        return (new ServiceResource($service))->resolve();
    }
}

