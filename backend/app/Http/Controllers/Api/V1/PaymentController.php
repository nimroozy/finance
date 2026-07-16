<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReversal;
use App\Services\Payments\PaymentReversalService;
use App\Services\Payments\PaymentService;
use App\Services\Zoho\ZohoPaymentSyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected PaymentReversalService $reversals,
        protected ZohoPaymentSyncService $zohoSync,
    ) {}

    public function methods(): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success($methods);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $data = $this->validatePaymentPayload($request);

        try {
            $preview = $this->payments->preview($data, Auth::user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payment' => [$e->getMessage()]]);
        }

        return ApiResponse::success($preview);
    }

    public function draft(Request $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $data = $this->validatePaymentPayload($request, requireIdempotency: true);

        try {
            $payment = $this->payments->createDraft($data, Auth::user());
        } catch (InvalidArgumentException|RuntimeException $e) {
            throw ValidationException::withMessages(['payment' => [$e->getMessage()]]);
        }

        return ApiResponse::success($payment, null, 201);
    }

    public function confirm(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)->firstOrFail();
        $this->authorize('confirm', $payment);

        $data = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $confirmed = $this->payments->confirm($uuid, Auth::user(), $data['idempotency_key'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            throw ValidationException::withMessages(['payment' => [$e->getMessage()]]);
        }

        return ApiResponse::success($confirmed);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $page = Payment::query()
            ->with(['method', 'customer:id,contact_name', 'collector.user:id,name', 'receipt:id,uuid,receipt_number,payment_id'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('collector_id'), fn ($q) => $q->where('collector_id', $request->integer('collector_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('zoho_sync_status'), fn ($q) => $q->where('zoho_sync_status', $request->string('zoho_sync_status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $payment = Payment::with([
            'method',
            'customer',
            'collector.user',
            'allocations.invoice',
            'receipt',
            'statusHistory',
            'syncAttempts',
            'latestReversal',
        ])->where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $payment);

        return ApiResponse::success($payment);
    }

    public function syncStatus(string $uuid): JsonResponse
    {
        $payment = Payment::with(['syncAttempts' => fn ($q) => $q->orderByDesc('id')])
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize('view', $payment);

        return ApiResponse::success([
            'uuid' => $payment->uuid,
            'status' => $payment->status,
            'zoho_sync_status' => $payment->zoho_sync_status,
            'zoho_payment_id' => $payment->zoho_payment_id,
            'sync_attempts' => $payment->sync_attempts,
            'last_sync_error' => $payment->last_sync_error,
            'attempts' => $payment->syncAttempts,
        ]);
    }

    public function retrySync(string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)->firstOrFail();
        $this->authorize('retrySync', $payment);

        try {
            $synced = $this->zohoSync->sync($payment);
        } catch (Throwable $e) {
            return ApiResponse::error('Zoho sync failed: '.$e->getMessage(), [], 422);
        }

        return ApiResponse::success($synced->fresh(['syncAttempts']));
    }

    public function requestReversal(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)->firstOrFail();
        $this->authorize('requestReversal', $payment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $reversal = $this->reversals->request($payment, Auth::user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => [$e->getMessage()]]);
        }

        return ApiResponse::success($reversal, null, 201);
    }

    public function approveReversal(int $id): JsonResponse
    {
        $reversal = PaymentReversal::query()->findOrFail($id);
        $this->authorize('approve', $reversal);

        try {
            $approved = $this->reversals->approve($reversal, Auth::user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reversal' => [$e->getMessage()]]);
        }

        return ApiResponse::success($approved);
    }

    public function rejectReversal(Request $request, int $id): JsonResponse
    {
        $reversal = PaymentReversal::query()->findOrFail($id);
        $this->authorize('reject', $reversal);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $rejected = $this->reversals->reject($reversal, Auth::user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => [$e->getMessage()]]);
        }

        return ApiResponse::success($rejected);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePaymentPayload(Request $request, bool $requireIdempotency = false): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'collector_id' => ['nullable', 'integer', 'exists:collectors,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'visit_id' => ['nullable', 'integer', 'exists:collection_visits,id'],
            'promise_id' => ['nullable', 'integer', 'exists:promise_to_pay,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'device_info' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => [$requireIdempotency ? 'required' : 'nullable', 'string', 'max:100'],
        ]);
    }
}
