<?php

namespace App\Services\Payments;

use App\Jobs\SyncPaymentToZohoJob;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatusHistory;
use App\Models\PromiseToPay;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Receipts\ReceiptService;
use App\Services\Wallets\CollectorWalletService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        protected AllocationService $allocations,
        protected PaymentStatusTransitionService $transitions,
        protected IdempotencyService $idempotency,
        protected CollectorWalletService $wallets,
        protected ReceiptService $receipts,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer:Customer,method:PaymentMethod,amount:string,allocations:list<array>,assignment:?CustomerAssignment}
     */
    public function preview(array $data, User $actor): array
    {
        $context = $this->resolveContext($data, $actor);
        $validatedAllocations = $this->allocations->validateAllocations(
            (int) $context['customer']->id,
            $context['amount'],
            $data['allocations'] ?? []
        );

        $lines = [];
        foreach ($validatedAllocations as $row) {
            $lines[] = [
                'invoice_id' => $row['invoice']->id,
                'invoice_number' => $row['invoice']->invoice_number,
                'amount' => $row['amount'],
                'effective_available' => $row['available'],
                'zoho_balance' => Money::normalize($row['invoice']->balance),
            ];
        }

        return [
            'customer_id' => $context['customer']->id,
            'collector_id' => $context['collector_id'],
            'branch_id' => $context['branch_id'],
            'assignment_id' => $context['assignment']?->id,
            'payment_method' => $context['method']->only(['id', 'code', 'name_en', 'affects_cash_wallet', 'requires_reference']),
            'currency' => $context['currency'],
            'amount' => $context['amount'],
            'allocations' => $lines,
            'warnings' => $this->buildWarnings($context, $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): Payment
    {
        $key = (string) ($data['idempotency_key'] ?? '');
        if ($key === '') {
            throw new InvalidArgumentException('idempotency_key is required.');
        }

        $wrapped = $this->idempotency->withKey($key, $actor, $this->idempotentPayload($data), function ($record) use ($data, $actor) {
            $context = $this->resolveContext($data, $actor);
            $validated = $this->allocations->validateAllocations(
                (int) $context['customer']->id,
                $context['amount'],
                $data['allocations'] ?? []
            );

            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'customer_id' => $context['customer']->id,
                'collector_id' => $context['collector_id'],
                'created_by' => $actor->id,
                'branch_id' => $context['branch_id'],
                'assignment_id' => $context['assignment']?->id,
                'visit_id' => $data['visit_id'] ?? null,
                'promise_id' => $data['promise_id'] ?? null,
                'payment_method_id' => $context['method']->id,
                'currency' => $context['currency'],
                'amount' => $context['amount'],
                'status' => Payment::STATUS_DRAFT,
                'zoho_sync_status' => Payment::ZOHO_PENDING,
                'idempotency_key' => $data['idempotency_key'],
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'gps_accuracy' => $data['gps_accuracy'] ?? null,
                'device_info' => $data['device_info'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'draft_expires_at' => now()->addHours((int) config('payments.draft_expiry_hours', 24)),
            ]);

            $this->allocations->persist($payment, $validated);

            PaymentStatusHistory::create([
                'payment_id' => $payment->id,
                'from_status' => null,
                'to_status' => Payment::STATUS_DRAFT,
                'changed_by' => $actor->id,
                'reason' => 'draft_created',
                'created_at' => now(),
            ]);

            $this->audit->log('payment.draft_created', $payment, null, $payment->toArray(), $payment->branch_id);

            $fresh = $payment->fresh(['allocations', 'method', 'customer']);
            $this->idempotency->complete($record, $fresh, [
                'success' => true,
                'data' => $fresh->toArray(),
            ], 201);

            return $fresh;
        });

        if ($wrapped['cached'] ?? false) {
            $body = $wrapped['response']['body'] ?? null;
            $paymentId = $body['data']['id'] ?? $wrapped['record']->payment_id ?? null;
            if ($paymentId) {
                return Payment::with(['allocations', 'method', 'customer'])->findOrFail($paymentId);
            }
            throw new RuntimeException('Cached idempotent draft response missing payment.');
        }

        return $wrapped['result'];
    }

    public function confirm(string $uuid, User $actor, ?string $confirmIdempotencyKey = null): Payment
    {
        return DB::transaction(function () use ($uuid, $actor, $confirmIdempotencyKey) {
            /** @var Payment $payment */
            $payment = Payment::withoutGlobalScopes()
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->isConfirmed() || $payment->isReversed()) {
                return $payment->fresh(['allocations', 'method', 'receipt', 'customer']);
            }

            if ($payment->status !== Payment::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft payments can be confirmed.');
            }

            if ($payment->draft_expires_at && $payment->draft_expires_at->isPast()) {
                $this->transitions->transition($payment, Payment::STATUS_EXPIRED, $actor, 'draft_expired');
                throw new InvalidArgumentException('Payment draft has expired.');
            }

            if ($confirmIdempotencyKey) {
                $confirmKey = 'confirm:'.$payment->uuid.':'.$confirmIdempotencyKey;
                $existing = $this->idempotency->find($confirmKey, $actor);
                if ($existing?->payment_id === $payment->id && $payment->isConfirmed()) {
                    return $payment->fresh(['allocations', 'method', 'receipt', 'customer']);
                }
            }

            $payment->load(['method', 'allocations.invoice', 'customer', 'assignment']);

            $this->assertConfirmable($payment, $actor);

            $allocRows = $payment->allocations->map(fn ($a) => [
                'invoice_id' => $a->invoice_id,
                'amount' => $a->amount,
            ])->all();

            $validated = $this->allocations->validateAllocations(
                (int) $payment->customer_id,
                Money::normalize($payment->amount),
                $allocRows,
                (int) $payment->id
            );

            // Re-persist with balance snapshots at confirm time.
            $this->allocations->persist($payment, $validated);

            $payment->payment_reference = $payment->payment_reference
                ?: $this->generateReference($payment);
            $payment->confirmed_by = $actor->id;
            $payment->confirmed_at = now();
            $payment->zoho_sync_status = Payment::ZOHO_PENDING;
            $payment->save();

            $method = $payment->method;
            $targetStatus = ($method && $method->affects_cash_wallet)
                ? Payment::STATUS_SETTLED_PENDING_HANDOVER
                : Payment::STATUS_PENDING_ZOHO_SYNC;

            $this->transitions->transition($payment, $targetStatus, $actor, 'confirmed_local');

            if ($method?->affects_cash_wallet && config('payments.wallet.enabled', true)) {
                $this->wallets->creditFromPayment($payment->fresh(), $actor);
            }

            if ($method?->receipt_enabled) {
                $this->receipts->issueForPayment($payment->fresh());
            }

            $this->maybeUpdatePromise($payment->fresh(), $actor);

            $fresh = $payment->fresh(['allocations.invoice', 'method', 'receipt', 'customer']);

            $this->audit->log('payment.confirmed', $fresh, null, $fresh->toArray(), $fresh->branch_id);

            SyncPaymentToZohoJob::dispatch($fresh->id);

            if ($confirmIdempotencyKey) {
                $record = $this->idempotency->begin(
                    'confirm:'.$payment->uuid.':'.$confirmIdempotencyKey,
                    $actor,
                    $this->idempotency->hashRequest(['uuid' => $payment->uuid, 'action' => 'confirm'])
                );
                $this->idempotency->complete($record, $fresh, [
                    'success' => true,
                    'data' => $fresh->toArray(),
                ]);
            }

            return $fresh;
        });
    }

    protected function assertConfirmable(Payment $payment, User $actor): void
    {
        $customer = $payment->customer;
        if (! $customer || $customer->is_unmapped || $customer->status !== Customer::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Customer is unmapped or inactive.');
        }

        if (! Money::isPositive($payment->amount)) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $assignment = $payment->assignment_id
            ? CustomerAssignment::withoutGlobalScopes()->find($payment->assignment_id)
            : CustomerAssignment::withoutGlobalScopes()
                ->where('customer_id', $payment->customer_id)
                ->where('collector_id', $payment->collector_id)
                ->where('is_active', true)
                ->first();

        if (! $assignment || ! $assignment->is_active) {
            throw new InvalidArgumentException('Collector must own an active assignment for this customer.');
        }

        if ((int) $assignment->collector_id !== (int) $payment->collector_id) {
            throw new InvalidArgumentException('Assignment collector mismatch.');
        }

        $method = $payment->method;
        if (! $method || ! $method->is_active) {
            throw new InvalidArgumentException('Payment method is inactive.');
        }

        if ($method->requires_reference && blank($payment->external_reference)) {
            throw new InvalidArgumentException('Payment method requires an external reference.');
        }

        if ($actor->isCollectorOnly()) {
            $collectorId = $actor->collector?->id;
            if (! $collectorId || (int) $collectorId !== (int) $payment->collector_id) {
                throw new RuntimeException('Collectors may only confirm their own payments.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{customer:Customer,method:PaymentMethod,amount:string,currency:string,collector_id:int,branch_id:int,assignment:?CustomerAssignment}
     */
    protected function resolveContext(array $data, User $actor): array
    {
        $customer = Customer::query()->findOrFail((int) $data['customer_id']);

        if ($customer->is_unmapped || $customer->status !== Customer::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Customer is unmapped or inactive.');
        }

        $method = PaymentMethod::query()->findOrFail((int) $data['payment_method_id']);
        if (! $method->is_active) {
            throw new InvalidArgumentException('Payment method is inactive.');
        }
        if ($actor->isCollectorOnly() && ! $method->collector_allowed) {
            throw new InvalidArgumentException('Payment method is not allowed for collectors.');
        }

        $amount = Money::normalize($data['amount'] ?? '0');
        if (! Money::isPositive($amount)) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $max = Money::normalize(config('payments.max_amount', '100000000'));
        if (Money::compare($amount, $max) === 1) {
            throw new InvalidArgumentException('Payment amount exceeds maximum allowed.');
        }

        $collectorId = (int) ($data['collector_id'] ?? 0);
        if ($actor->isCollectorOnly()) {
            $collectorId = (int) $actor->collector?->id;
        }
        if ($collectorId <= 0) {
            throw new InvalidArgumentException('collector_id is required.');
        }

        $assignment = CustomerAssignment::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('collector_id', $collectorId)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            throw new InvalidArgumentException('Collector must own an active assignment for this customer.');
        }

        if ($method->requires_reference && blank($data['external_reference'] ?? null)) {
            throw new InvalidArgumentException('Payment method requires an external reference.');
        }

        $currency = strtoupper((string) ($data['currency'] ?? $customer->currency ?? config('payments.default_currency', 'AFN')));

        return [
            'customer' => $customer,
            'method' => $method,
            'amount' => $amount,
            'currency' => $currency,
            'collector_id' => $collectorId,
            'branch_id' => (int) $customer->branch_id,
            'assignment' => $assignment,
        ];
    }

    protected function generateReference(Payment $payment): string
    {
        return sprintf('PAY-%s-%s', $payment->branch_id, strtoupper(Str::random(8)));
    }

    protected function maybeUpdatePromise(Payment $payment, User $actor): void
    {
        if (! $payment->promise_id) {
            return;
        }

        $promise = PromiseToPay::withoutGlobalScopes()->find($payment->promise_id);
        if (! $promise || ! in_array($promise->status, PromiseToPay::OPEN_STATUSES, true)) {
            return;
        }

        if (Money::compare($payment->amount, $promise->amount) >= 0) {
            $promise->status = PromiseToPay::STATUS_FULFILLED;
            $promise->fulfilled_at = now();
            $promise->fulfilled_by = $actor->id;
            $promise->save();
        } else {
            $promise->status = PromiseToPay::STATUS_PARTIALLY_FULFILLED;
            $promise->save();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected function buildWarnings(array $context, array $data): array
    {
        $warnings = [];
        if (! empty($data['promise_id'])) {
            $warnings[] = 'Linking a promise is optional; amount may mark it partially_fulfilled.';
        }
        if ($context['method']->affects_cash_wallet) {
            $warnings[] = 'Cash payment will credit collector wallet and status settled_pending_handover.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function idempotentPayload(array $data): array
    {
        return [
            'customer_id' => $data['customer_id'] ?? null,
            'collector_id' => $data['collector_id'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'amount' => isset($data['amount']) ? Money::normalize($data['amount']) : null,
            'currency' => $data['currency'] ?? null,
            'allocations' => $data['allocations'] ?? [],
            'external_reference' => $data['external_reference'] ?? null,
            'promise_id' => $data['promise_id'] ?? null,
            'visit_id' => $data['visit_id'] ?? null,
        ];
    }
}
