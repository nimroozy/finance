<?php

namespace App\Services\Zoho;

use App\Models\Payment;
use App\Models\PaymentSyncAttempt;
use App\Models\ZohoPaymentModeMapping;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentStatusTransitionService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ZohoPaymentSyncService
{
    public function __construct(
        protected ZohoApiClient $client,
        protected PaymentStatusTransitionService $transitions,
        protected AuditLogger $audit,
    ) {}

    public function isDryRun(): bool
    {
        return (bool) config('zoho.payments.dry_run', false);
    }

    public function sync(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            /** @var Payment $locked */
            $locked = Payment::withoutGlobalScopes()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isReversed()) {
                return $locked;
            }

            if ($locked->zoho_sync_status === Payment::ZOHO_SYNCED && $locked->zoho_payment_id) {
                return $locked;
            }

            if (! $locked->isConfirmed()) {
                return $locked;
            }

            $locked->load(['customer', 'method', 'allocations.invoice']);
            $attemptNumber = (int) $locked->sync_attempts + 1;
            $locked->sync_attempts = $attemptNumber;
            $locked->zoho_sync_status = Payment::ZOHO_SYNCING;
            $locked->save();

            $payload = $this->buildPayload($locked);
            $started = microtime(true);

            try {
                if ($this->isDryRun()) {
                    $fakeId = 'DRYRUN-'.Str::upper(Str::random(12));
                    $duration = (int) ((microtime(true) - $started) * 1000);

                    PaymentSyncAttempt::create([
                        'payment_id' => $locked->id,
                        'attempt_number' => $attemptNumber,
                        'status' => PaymentSyncAttempt::STATUS_DRY_RUN,
                        'zoho_payment_id' => $fakeId,
                        'request_payload' => $payload,
                        'response_payload' => ['dry_run' => true, 'payment_id' => $fakeId],
                        'is_dry_run' => true,
                        'duration_ms' => $duration,
                    ]);

                    $locked->zoho_payment_id = $fakeId;
                    $locked->zoho_reference = $fakeId;
                    $locked->zoho_sync_status = Payment::ZOHO_DRY_RUN;
                    $locked->last_sync_error = null;
                    $locked->save();

                    if ($locked->status !== Payment::STATUS_SETTLED_PENDING_HANDOVER) {
                        $this->transitions->transition($locked, Payment::STATUS_SYNCED, null, 'zoho_dry_run');
                    }

                    $this->audit->log('payment.zoho_dry_run', $locked, null, ['zoho_payment_id' => $fakeId], $locked->branch_id);

                    return $locked->fresh();
                }

                $endpoint = (string) config('zoho.payments.endpoint', 'customerpayments');
                $response = $this->client->post($endpoint, $payload, 'payment');
                $duration = (int) ((microtime(true) - $started) * 1000);

                $zohoPayment = $response['payment'] ?? $response['customerpayment'] ?? $response;
                $zohoId = (string) ($zohoPayment['payment_id'] ?? $zohoPayment['customerpayment_id'] ?? $zohoPayment['payment_number'] ?? '');

                PaymentSyncAttempt::create([
                    'payment_id' => $locked->id,
                    'attempt_number' => $attemptNumber,
                    'status' => PaymentSyncAttempt::STATUS_SUCCESS,
                    'zoho_payment_id' => $zohoId ?: null,
                    'request_payload' => $payload,
                    'response_payload' => $response,
                    'is_dry_run' => false,
                    'duration_ms' => $duration,
                ]);

                $locked->zoho_payment_id = $zohoId ?: null;
                $locked->zoho_reference = $zohoPayment['payment_number'] ?? $zohoId;
                $locked->zoho_sync_status = Payment::ZOHO_SYNCED;
                $locked->last_sync_error = null;
                $locked->save();

                if ($locked->status !== Payment::STATUS_SETTLED_PENDING_HANDOVER
                    && $locked->status !== Payment::STATUS_SYNCED) {
                    $this->transitions->transition($locked, Payment::STATUS_SYNCED, null, 'zoho_synced');
                } elseif ($locked->status === Payment::STATUS_SETTLED_PENDING_HANDOVER) {
                    // Keep cash local status; only zoho_sync_status reflects sync.
                    $locked->refresh();
                } else {
                    $this->transitions->transition($locked, Payment::STATUS_SYNCED, null, 'zoho_synced');
                }

                $this->audit->log('payment.zoho_synced', $locked, null, ['zoho_payment_id' => $zohoId], $locked->branch_id);

                return $locked->fresh();
            } catch (Throwable $e) {
                $duration = (int) ((microtime(true) - $started) * 1000);

                PaymentSyncAttempt::create([
                    'payment_id' => $locked->id,
                    'attempt_number' => $attemptNumber,
                    'status' => PaymentSyncAttempt::STATUS_FAILED,
                    'request_payload' => $payload,
                    'error_message' => $e->getMessage(),
                    'is_dry_run' => false,
                    'duration_ms' => $duration,
                ]);

                $locked->zoho_sync_status = Payment::ZOHO_FAILED;
                $locked->last_sync_error = $e->getMessage();
                $locked->save();

                if ($locked->status !== Payment::STATUS_SETTLED_PENDING_HANDOVER) {
                    try {
                        $this->transitions->transition($locked, Payment::STATUS_SYNC_FAILED, null, 'zoho_sync_failed');
                    } catch (Throwable) {
                        // Already in a terminal-ish status.
                    }
                }

                $this->audit->log('payment.zoho_sync_failed', $locked, null, ['error' => $e->getMessage()], $locked->branch_id);

                throw $e;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(Payment $payment): array
    {
        $customer = $payment->customer;
        $invoices = [];
        foreach ($payment->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if (! $invoice?->zoho_invoice_id) {
                continue;
            }
            $invoices[] = [
                'invoice_id' => $invoice->zoho_invoice_id,
                'amount_applied' => Money::normalize($allocation->amount),
            ];
        }

        $mode = $this->resolveZohoPaymentMode($payment);

        return [
            'customer_id' => $customer?->zoho_contact_id,
            'payment_mode' => $mode,
            'amount' => Money::normalize($payment->amount),
            'date' => optional($payment->confirmed_at)->toDateString() ?? now()->toDateString(),
            'reference_number' => $payment->payment_reference,
            'description' => $payment->notes,
            'invoices' => $invoices,
        ];
    }

    protected function resolveZohoPaymentMode(Payment $payment): string
    {
        $method = $payment->method;
        if (! $method) {
            return 'Cash';
        }

        $mapping = ZohoPaymentModeMapping::query()
            ->where('payment_method_id', $method->id)
            ->where('is_active', true)
            ->first();

        if ($mapping) {
            return $mapping->name;
        }

        $byLocal = ZohoPaymentModeMapping::query()
            ->where('local_method', $method->code)
            ->where('is_active', true)
            ->first();

        return $byLocal?->name ?: ucfirst(str_replace('_', ' ', $method->code));
    }
}
