<?php

namespace App\Services\Zoho;

use App\Events\BusinessNotificationRequested;
use App\Jobs\RefreshZohoInvoicesJob;
use App\Models\Branch;
use App\Models\BranchPaymentConfiguration;
use App\Models\BranchZohoAccountMapping;
use App\Models\Payment;
use App\Models\PaymentSyncAttempt;
use App\Models\ZohoPaymentModeMapping;
use App\Services\AuditLogger;
use App\Services\Ownership\BranchPaymentMappingService;
use App\Services\Payments\PaymentStatusTransitionService;
use App\Support\LocalizedInvalidArgumentException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZohoPaymentSyncService
{
    public function __construct(
        protected ZohoApiClient $client,
        protected PaymentStatusTransitionService $transitions,
        protected AuditLogger $audit,
        protected BranchPaymentMappingService $branchPayments,
    ) {}

    public function isDryRun(): bool
    {
        return (bool) config('zoho.payments.dry_run', false);
    }

    /**
     * @param  bool  $forceLive  Super-admin / smoke scoped bypass. Does not change env for collectors.
     */
    public function sync(Payment $payment, bool $forceLive = false): Payment
    {
        return DB::transaction(function () use ($payment, $forceLive) {
            /** @var Payment $locked */
            $locked = Payment::withoutGlobalScopes()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isReversed()) {
                return $locked;
            }

            if ($this->hasRealZohoPaymentId($locked) && $locked->zoho_sync_status === Payment::ZOHO_SYNCED) {
                $this->audit->log('payment.zoho_sync_idempotent', $locked, null, [
                    'zoho_payment_id' => $locked->zoho_payment_id,
                ], $locked->branch_id);

                return $locked;
            }

            if (! $locked->isConfirmed()) {
                return $locked;
            }

            $effectiveDryRun = $this->isDryRun() && ! $forceLive;

            // Promote a previous dry-run placeholder when forcing live.
            if ($forceLive && $this->isDryRunPlaceholder($locked)) {
                $locked->zoho_payment_id = null;
                $locked->zoho_reference = null;
                $locked->zoho_sync_status = Payment::ZOHO_PENDING;
                $locked->save();
            }

            $locked->load(['customer', 'method', 'allocations.invoice']);
            $attemptNumber = (int) $locked->sync_attempts + 1;
            $locked->sync_attempts = $attemptNumber;
            $locked->zoho_sync_status = Payment::ZOHO_SYNCING;
            $locked->save();

            if (! $effectiveDryRun) {
                try {
                    $mapping = $this->branchPayments->assertReadyForLivePayment((int) $locked->branch_id);
                    $this->snapshotMapping($locked, $mapping);
                } catch (Throwable $e) {
                    // forceLive smoke/admin path may use configured default account when mapping is incomplete.
                    if ($forceLive && trim((string) config('zoho.payments.default_account_id', '')) !== '') {
                        $this->audit->log('payment.zoho_mapping_fallback_default', $locked, null, [
                            'error' => $e->getMessage(),
                        ], $locked->branch_id);
                    } else {
                        $locked->zoho_sync_status = Payment::ZOHO_FAILED;
                        $locked->last_sync_error = $e->getMessage();
                        $locked->save();
                        $this->audit->log('payment.zoho_blocked_mapping', $locked, null, [
                            'error' => $e->getMessage(),
                        ], $locked->branch_id);
                        throw $e;
                    }
                }
            } else {
                $mapping = BranchZohoAccountMapping::query()->where('branch_id', $locked->branch_id)->first();
                if ($mapping) {
                    $this->snapshotMapping($locked, $mapping);
                }
            }

            $payload = $this->buildPayload($locked);
            if (! $effectiveDryRun) {
                $this->assertLocationAccountPreflight($locked, $payload);
            }
            $started = microtime(true);

            try {
                if ($effectiveDryRun) {
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

                // Ambiguous / retry safety: search Zoho by local reference before creating.
                $existing = $this->findExistingByReference((string) $locked->payment_reference);
                if ($existing !== null) {
                    return $this->attachExistingZohoPayment($locked, $existing, $payload, $attemptNumber, $started, reconciled: true);
                }

                $endpoint = (string) config('zoho.payments.endpoint', 'customerpayments');
                $response = $this->client->post($endpoint, $payload, 'payment');
                $duration = (int) ((microtime(true) - $started) * 1000);

                $zohoPayment = $response['payment'] ?? $response['customerpayment'] ?? $response;
                $zohoId = (string) ($zohoPayment['payment_id'] ?? $zohoPayment['customerpayment_id'] ?? '');

                if ($zohoId === '') {
                    throw new RuntimeException('Zoho payment response missing payment_id.');
                }

                PaymentSyncAttempt::create([
                    'payment_id' => $locked->id,
                    'attempt_number' => $attemptNumber,
                    'status' => PaymentSyncAttempt::STATUS_SUCCESS,
                    'zoho_payment_id' => $zohoId,
                    'request_payload' => $payload,
                    'response_payload' => $this->sanitizeResponse($response),
                    'is_dry_run' => false,
                    'duration_ms' => $duration,
                ]);

                $locked->zoho_payment_id = $zohoId;
                $locked->zoho_reference = (string) ($zohoPayment['payment_number'] ?? $zohoId);
                $locked->zoho_sync_status = Payment::ZOHO_SYNCED;
                $locked->last_sync_error = null;
                $locked->save();

                if ($locked->status !== Payment::STATUS_SETTLED_PENDING_HANDOVER
                    && $locked->status !== Payment::STATUS_SYNCED) {
                    $this->transitions->transition($locked, Payment::STATUS_SYNCED, null, 'zoho_synced');
                }

                $this->audit->log('payment.zoho_synced', $locked, null, ['zoho_payment_id' => $zohoId], $locked->branch_id);
                $this->queueInvoiceRefresh($locked);

                $locked->loadMissing('customer');
                $phone = $locked->customer?->mobile ?: $locked->customer?->phone;
                if ($phone) {
                    BusinessNotificationRequested::dispatch('payment_posted', [
                        'branch_id' => $locked->branch_id,
                        'customer_id' => $locked->customer_id,
                        'payment_id' => $locked->id,
                        'phone' => $phone,
                        'title' => 'Payment posted',
                        'body' => 'Your payment was posted successfully.',
                    ]);
                }

                return $locked->fresh();
            } catch (Throwable $e) {
                // Lost-response safety: another concurrent create may have succeeded.
                $existing = $this->findExistingByReference((string) $locked->payment_reference);
                if ($existing !== null) {
                    return $this->attachExistingZohoPayment($locked, $existing, $payload, $attemptNumber, $started, reconciled: true);
                }

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

                BusinessNotificationRequested::dispatch('zoho_sync_failed', [
                    'branch_id' => $locked->branch_id,
                    'payment_id' => $locked->id,
                    'customer_id' => $locked->customer_id,
                    'title' => 'Zoho sync failed',
                    'body' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Delete/void a Zoho customer payment. Returns true when removed or already absent.
     *
     * @return array{ok:bool,manual_review:bool,error:?string,zoho_payment_id:?string}
     */
    public function voidPayment(Payment $payment, bool $forceLive = false): array
    {
        $zohoId = (string) ($payment->zoho_payment_id ?? '');
        if ($zohoId === '' || $this->isDryRunPlaceholder($payment)) {
            return ['ok' => true, 'manual_review' => false, 'error' => null, 'zoho_payment_id' => null];
        }

        // Real Zoho payment IDs must always be voided — dry-run only skips create, not cleanup of live posts.
        try {
            $endpoint = (string) config('zoho.payments.endpoint', 'customerpayments');
            $this->client->delete($endpoint.'/'.$zohoId, [], 'payment');
            $this->audit->log('payment.zoho_voided', $payment, null, ['zoho_payment_id' => $zohoId], $payment->branch_id);

            return ['ok' => true, 'manual_review' => false, 'error' => null, 'zoho_payment_id' => $zohoId];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            // Idempotent void: already deleted / not found is success.
            if (preg_match('/\b(404|1002|not found|does not exist|resource not found)\b/i', $message)) {
                $this->audit->log('payment.zoho_voided', $payment, null, [
                    'zoho_payment_id' => $zohoId,
                    'idempotent' => true,
                ], $payment->branch_id);

                return ['ok' => true, 'manual_review' => false, 'error' => null, 'zoho_payment_id' => $zohoId];
            }

            $this->audit->log('payment.zoho_void_failed', $payment, null, [
                'zoho_payment_id' => $zohoId,
                'error' => $message,
            ], $payment->branch_id);

            return [
                'ok' => false,
                'manual_review' => true,
                'error' => $message,
                'zoho_payment_id' => $zohoId,
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findExistingByReference(string $reference): ?array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        try {
            $endpoint = (string) config('zoho.payments.endpoint', 'customerpayments');
            $response = $this->client->get($endpoint, [
                'reference_number' => $reference,
                'per_page' => 25,
            ], 'payment');
            $payments = $response['customerpayments'] ?? $response['payments'] ?? [];
            if (! is_array($payments)) {
                return null;
            }

            foreach ($payments as $row) {
                $ref = (string) ($row['reference_number'] ?? '');
                if ($ref === $reference && ! empty($row['payment_id'])) {
                    return $row;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Compare local payment with Zoho payment payload.
     *
     * @return array{status:string,details:array<string,mixed>}
     */
    public function reconcileAgainstZoho(Payment $payment): array
    {
        $payment->load(['customer', 'allocations.invoice']);
        $zohoId = (string) ($payment->zoho_payment_id ?? '');

        if ($zohoId === '' || $this->isDryRunPlaceholder($payment)) {
            return [
                'status' => 'missing_in_zoho',
                'details' => ['reason' => 'no_real_zoho_payment_id'],
            ];
        }

        try {
            $endpoint = (string) config('zoho.payments.endpoint', 'customerpayments');
            $response = $this->client->get($endpoint.'/'.$zohoId, [], 'payment');
            $zoho = $response['payment'] ?? $response['customerpayment'] ?? $response;
            $amount = Money::normalize((string) ($zoho['amount'] ?? '0'));
            $customerId = (string) ($zoho['customer_id'] ?? '');
            $localAmount = Money::normalize($payment->amount);
            $localCustomer = (string) ($payment->customer?->zoho_contact_id ?? '');

            $status = 'matched';
            if (! Money::equals($amount, $localAmount)) {
                $status = 'amount_mismatch';
            } elseif ($customerId !== '' && $localCustomer !== '' && $customerId !== $localCustomer) {
                $status = 'customer_mismatch';
            }

            return [
                'status' => $status,
                'details' => [
                    'local_amount' => $localAmount,
                    'zoho_amount' => $amount,
                    'local_customer_id' => $localCustomer,
                    'zoho_customer_id' => $customerId,
                    'zoho_payment_id' => $zohoId,
                    'zoho_reference' => $zoho['payment_number'] ?? null,
                    'payment_mode' => $zoho['payment_mode'] ?? null,
                    'account_id' => $zoho['account_id'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'manual_review',
                'details' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $payload
     */
    protected function attachExistingZohoPayment(
        Payment $locked,
        array $existing,
        array $payload,
        int $attemptNumber,
        float $started,
        bool $reconciled = false,
    ): Payment {
        $zohoId = (string) ($existing['payment_id'] ?? '');
        $duration = (int) ((microtime(true) - $started) * 1000);

        PaymentSyncAttempt::create([
            'payment_id' => $locked->id,
            'attempt_number' => $attemptNumber,
            'status' => PaymentSyncAttempt::STATUS_SUCCESS,
            'zoho_payment_id' => $zohoId,
            'request_payload' => $payload,
            'response_payload' => [
                'reconciled_existing' => $reconciled,
                'payment' => $this->sanitizeResponse($existing),
            ],
            'is_dry_run' => false,
            'duration_ms' => $duration,
        ]);

        $locked->zoho_payment_id = $zohoId;
        $locked->zoho_reference = (string) ($existing['payment_number'] ?? $zohoId);
        $locked->zoho_sync_status = Payment::ZOHO_SYNCED;
        $locked->last_sync_error = null;
        $locked->save();

        if ($locked->status !== Payment::STATUS_SETTLED_PENDING_HANDOVER
            && $locked->status !== Payment::STATUS_SYNCED) {
            try {
                $this->transitions->transition($locked, Payment::STATUS_SYNCED, null, 'zoho_reconciled_existing');
            } catch (Throwable) {
            }
        }

        $this->audit->log('payment.zoho_sync_reconciled_existing', $locked, null, [
            'zoho_payment_id' => $zohoId,
        ], $locked->branch_id);

        $this->queueInvoiceRefresh($locked);

        return $locked->fresh();
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

        $mode = $payment->zoho_payment_mode_used ?: $this->resolveZohoPaymentMode($payment);
        $accountId = $payment->zoho_account_id_snapshot ?: $this->resolveDepositAccountId($payment);

        $payload = [
            'customer_id' => $customer?->zoho_contact_id,
            'payment_mode' => $mode,
            'amount' => Money::normalize($payment->amount),
            'date' => optional($payment->confirmed_at)->toDateString() ?? now()->toDateString(),
            'reference_number' => $payment->payment_reference,
            'description' => $payment->notes,
            'invoices' => $invoices,
        ];

        if ($accountId) {
            $payload['account_id'] = $accountId;
        }
        if ($payment->zoho_location_id_used) {
            $payload['location_id'] = $payment->zoho_location_id_used;
        }

        $this->audit->log('payment.zoho_account_selected', $payment, null, [
            'account_id' => $accountId,
            'payment_mode' => $mode,
            'location_id' => $payment->zoho_location_id_used,
            'mapping_version' => $payment->mapping_version_used,
        ], $payment->branch_id);

        return $payload;
    }

    /**
     * Block Zoho HTTP when invoice location / currency does not match branch mapping.
     *
     * @param  array<string, mixed>  $payload
     */
    public function assertLocationAccountPreflight(Payment $payment, array $payload = []): void
    {
        $payment->loadMissing(['allocations.invoice', 'customer']);
        $mappingLocation = (string) ($payment->zoho_location_id_used ?? '');
        $mappingAccount = (string) ($payment->zoho_account_id_snapshot ?? ($payload['account_id'] ?? ''));
        $paymentCurrency = strtoupper((string) $payment->currency);

        $branch = Branch::withoutGlobalScopes()->find($payment->branch_id);
        $branchNameEn = $branch?->name_en ?: $branch?->code ?: 'this branch';
        $branchNameFa = $branch?->name_fa ?: $branchNameEn;

        foreach ($payment->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if (! $invoice) {
                continue;
            }

            $invoiceLocation = (string) ($invoice->zoho_location_id ?? '');
            $invoiceCurrency = strtoupper((string) ($invoice->currency ?? ''));

            if ($invoiceCurrency !== '' && $paymentCurrency !== '' && $invoiceCurrency !== $paymentCurrency) {
                throw new LocalizedInvalidArgumentException(
                    "Invoice {$invoice->invoice_number} currency ({$invoiceCurrency}) does not match payment currency ({$paymentCurrency}).",
                    "ارز فاکتور {$invoice->invoice_number} ({$invoiceCurrency}) با ارز پرداخت ({$paymentCurrency}) همخوانی ندارد.",
                    ['invoice_id' => $invoice->id, 'invoice_currency' => $invoiceCurrency, 'payment_currency' => $paymentCurrency]
                );
            }

            if ($mappingLocation !== '' && $invoiceLocation !== '' && $invoiceLocation !== $mappingLocation) {
                $invoiceBranch = Branch::withoutGlobalScopes()
                    ->where('zoho_location_id', $invoiceLocation)
                    ->first();
                $invoiceBranchEn = $invoiceBranch?->name_en ?: $invoiceBranch?->code ?: 'another location';
                $invoiceBranchFa = $invoiceBranch?->name_fa ?: $invoiceBranchEn;
                $accountLabel = $payment->zoho_account_name_snapshot ?: 'cash account';

                throw new LocalizedInvalidArgumentException(
                    "The selected invoice belongs to {$invoiceBranchEn}, but this payment is configured for the {$branchNameEn} {$accountLabel}.",
                    "فاکتور انتخاب‌شده متعلق به {$invoiceBranchFa} است، اما این پرداخت برای حساب نقدی {$branchNameFa} پیکربندی شده است.",
                    [
                        'invoice_id' => $invoice->id,
                        'invoice_location_id' => $invoiceLocation,
                        'mapping_location_id' => $mappingLocation,
                        'account_id' => $mappingAccount,
                    ]
                );
            }

            // Customer number prefix vs invoice location
            $customer = $payment->customer;
            $effectiveNumber = app(\App\Services\Mapping\CustomerNumberPrefixMatcher::class)
                ->effectiveCustomerNumber($customer?->customer_number, $customer?->contact_name);
            if ($effectiveNumber && $invoiceLocation !== '') {
                $prefixHit = app(\App\Services\Mapping\CustomerNumberPrefixMatcher::class)->detect($effectiveNumber);
                if ($prefixHit && empty($prefixHit['ambiguous']) && ! empty($prefixHit['branch_id'])) {
                    $prefixBranch = Branch::withoutGlobalScopes()->find($prefixHit['branch_id']);
                    $invoiceBranch = Branch::withoutGlobalScopes()->where('zoho_location_id', $invoiceLocation)->first();
                    if ($prefixBranch && $invoiceBranch && (int) $prefixBranch->id !== (int) $invoiceBranch->id) {
                        $num = $effectiveNumber;
                        throw new LocalizedInvalidArgumentException(
                            "Customer number {$num} belongs to ".($prefixBranch->name_en ?: $prefixBranch->code).", but the selected invoice belongs to ".($invoiceBranch->name_en ?: $invoiceBranch->code).'.',
                            "شماره مشتری {$num} متعلق به ".($prefixBranch->name_fa ?: $prefixBranch->code).' است، اما فاکتور انتخاب‌شده متعلق به '.($invoiceBranch->name_fa ?: $invoiceBranch->code).' است.',
                            [
                                'customer_id' => $customer->id,
                                'customer_number' => $num,
                                'prefix_branch_id' => $prefixBranch->id,
                                'invoice_branch_id' => $invoiceBranch->id,
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function queueInvoiceRefresh(Payment $payment): void
    {
        $payment->loadMissing('allocations.invoice');
        $ids = $payment->allocations
            ->map(fn ($a) => $a->invoice?->zoho_invoice_id)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        try {
            app(\App\Services\Zoho\ZohoInvoiceSyncService::class)->markAwaitingRefresh($ids);
        } catch (Throwable) {
        }

        RefreshZohoInvoicesJob::dispatch($ids, $payment->id);
    }

    protected function snapshotMapping(Payment $payment, BranchZohoAccountMapping $mapping): void
    {
        $config = BranchPaymentConfiguration::query()->where('branch_id', $payment->branch_id)->first();
        $payment->branch_payment_configuration_id = $config?->id;
        $payment->zoho_account_id_snapshot = $mapping->zoho_account_id;
        $payment->zoho_account_name_snapshot = $mapping->zoho_account_name;
        $payment->zoho_location_id_used = $mapping->zoho_location_id;
        $payment->zoho_payment_mode_used = $mapping->zoho_payment_mode_name;
        $payment->mapping_version_used = $mapping->mapping_version;
        $payment->mapping_validated_at = $mapping->last_validated_at;
        $payment->save();
    }

    protected function resolveDepositAccountId(?Payment $payment = null): ?string
    {
        if ($payment?->zoho_account_id_snapshot) {
            return $payment->zoho_account_id_snapshot;
        }
        if ($payment?->branch_id) {
            $mapping = BranchZohoAccountMapping::query()->where('branch_id', $payment->branch_id)->where('is_active', true)->first();
            if ($mapping?->zoho_account_id) {
                return $mapping->zoho_account_id;
            }
        }
        $configured = trim((string) config('zoho.payments.default_account_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        return null;
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

    protected function hasRealZohoPaymentId(Payment $payment): bool
    {
        return filled($payment->zoho_payment_id) && ! $this->isDryRunPlaceholder($payment);
    }

    protected function isDryRunPlaceholder(Payment $payment): bool
    {
        $id = (string) ($payment->zoho_payment_id ?? '');

        return $payment->zoho_sync_status === Payment::ZOHO_DRY_RUN
            || str_starts_with($id, 'DRYRUN-');
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function sanitizeResponse(array $response): array
    {
        unset($response['access_token'], $response['refresh_token']);

        return $response;
    }
}
