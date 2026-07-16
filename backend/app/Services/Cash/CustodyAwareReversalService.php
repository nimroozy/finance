<?php

namespace App\Services\Cash;

use App\Jobs\RefreshZohoInvoicesJob;
use App\Jobs\RetryCustodyZohoVoidJob;
use App\Models\BranchCashbox;
use App\Models\CashHandoverItem;
use App\Models\CashHandoverRequest;
use App\Models\Collector;
use App\Models\CustodyConflict;
use App\Models\Payment;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentReversal;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Payments\AllocationService;
use App\Services\Payments\PaymentStatusTransitionService;
use App\Services\Receipts\ReceiptService;
use App\Services\Zoho\ZohoPaymentSyncService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class CustodyAwareReversalService
{
    public const STATUS_PENDING = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_MANUAL_REVIEW = 'manual_review';

    public const STATUS_CRITICAL_RECOVERY = 'critical_recovery';

    public const ZOHO_NOT_REQUIRED = 'not_required';

    public const ZOHO_PENDING = 'pending';

    public const ZOHO_VOIDED = 'voided';

    public const ZOHO_FAILED = 'failed';

    public function __construct(
        private BranchCashboxService $cashboxes,
        private AllocationService $allocations,
        private PaymentStatusTransitionService $transitions,
        private ReceiptService $receipts,
        private ZohoPaymentSyncService $zohoSync,
        private AuditLogger $audit,
    ) {}

    public function requiresCustodyReview(Payment $payment): bool
    {
        return $payment->handover_status === 'handed_over'
            || $payment->handover_status === 'partially_handed_over';
    }

    public function createConflict(Payment $payment, PaymentReversal $reversal, User $actor, string $reason): CustodyConflict
    {
        $conflict = CustodyConflict::withoutGlobalScopes()->updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'handover_id' => $payment->cash_handover_request_id,
                'reversal_id' => $reversal->id,
                'branch_id' => $payment->branch_id,
                'status' => self::STATUS_PENDING,
                'reason' => $reason,
                'requested_by' => $actor->id,
                'zoho_void_status' => self::ZOHO_PENDING,
                'approved_amount' => $payment->amount,
                'details' => $this->snapshotDetails($payment),
            ]
        );

        $this->audit->log('custody_reversal.requested', $conflict, null, $conflict->toArray(), $payment->branch_id, $reason);

        return $conflict->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(CustodyConflict $conflict): array
    {
        $conflict->loadMissing([
            'payment.customer',
            'payment.method',
            'payment.allocations.invoice',
            'handover',
            'reversal',
            'requester',
            'reviewer',
        ]);

        $payment = $conflict->payment;
        $handover = $conflict->handover;
        $cashbox = BranchCashbox::withoutGlobalScopes()
            ->where('branch_id', $conflict->branch_id)
            ->where('currency', $payment?->currency ?? 'AFN')
            ->first();
        $collector = $payment?->collector_id
            ? Collector::query()->with('user:id,name,email')->find($payment->collector_id)
            : null;
        $receipt = $payment?->receipt_id
            ? Receipt::withoutGlobalScopes()->find($payment->receipt_id)
            : null;

        $zohoState = null;
        if ($payment && filled($payment->zoho_payment_id) && ! str_starts_with((string) $payment->zoho_payment_id, 'DRYRUN-')) {
            try {
                $zohoState = $this->zohoSync->reconcileAgainstZoho($payment);
            } catch (Throwable $e) {
                $zohoState = ['status' => 'manual_review', 'details' => ['error' => $e->getMessage()]];
            }
        }

        return [
            'conflict' => $conflict,
            'payment' => $payment,
            'receipt' => $receipt,
            'handover' => $handover,
            'collector' => $collector,
            'cashbox' => $cashbox,
            'amount' => $payment?->amount,
            'currency' => $payment?->currency,
            'reason' => $conflict->reason,
            'zoho_state' => $zohoState,
            'original_handover_amount' => $handover?->approved_amount ?? $handover?->selected_payment_total,
        ];
    }

    public function approve(CustodyConflict $conflict, User $actor, bool $forceLiveZoho = false): CustodyConflict
    {
        if (! $actor->can('custody_reversals.review') && ! $actor->can('reversals.approve')) {
            throw new InvalidArgumentException('Unauthorized custody reversal approval.');
        }

        $zohoAlreadyGone = false;
        $paymentId = null;

        $result = DB::transaction(function () use ($conflict, $actor, &$zohoAlreadyGone, &$paymentId) {
            /** @var CustodyConflict $locked */
            $locked = CustodyConflict::withoutGlobalScopes()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [self::STATUS_PENDING, self::STATUS_CRITICAL_RECOVERY], true)) {
                throw new InvalidArgumentException('Custody reversal is not pending review.');
            }

            /** @var Payment $payment */
            $payment = Payment::withoutGlobalScopes()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            $paymentId = $payment->id;

            if ($payment->isReversed()) {
                throw new InvalidArgumentException('Payment is already reversed.');
            }

            if (! $this->requiresCustodyReview($payment)) {
                throw new InvalidArgumentException('Payment is not in handed-over custody.');
            }

            $amount = Money::normalize((string) ($locked->approved_amount ?: $payment->amount));
            $cashbox = $this->cashboxes->ensure((int) $payment->branch_id, (string) $payment->currency, $actor);
            $cashbox = BranchCashbox::withoutGlobalScopes()->whereKey($cashbox->id)->lockForUpdate()->firstOrFail();

            if (Money::compare(Money::normalize((string) $cashbox->balance), $amount) === -1) {
                $locked->status = self::STATUS_MANUAL_REVIEW;
                $locked->cashbox_insufficient = true;
                $locked->manual_review_reason = 'Insufficient branch cashbox balance for custody reversal debit.';
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = now();
                $locked->save();
                $this->audit->log('custody_reversal.manual_review_insufficient_cashbox', $locked, null, [
                    'cashbox_balance' => $cashbox->balance,
                    'required' => $amount,
                ], $locked->branch_id);

                return $locked->fresh();
            }

            $zohoAlreadyGone = $locked->status === self::STATUS_CRITICAL_RECOVERY
                || $locked->zoho_void_status === self::ZOHO_VOIDED;

            try {
                $this->executeLocalCustodyReversal($locked, $payment, $cashbox, $actor, $amount);
            } catch (Throwable $e) {
                if ($zohoAlreadyGone) {
                    $locked->status = self::STATUS_CRITICAL_RECOVERY;
                    $locked->critical_alert = true;
                    $locked->critical_alert_message = $e->getMessage();
                    $locked->reviewed_by = $actor->id;
                    $locked->reviewed_at = now();
                    $locked->save();
                    $this->audit->log('custody_reversal.critical_recovery', $locked, null, [
                        'error' => $e->getMessage(),
                    ], $locked->branch_id);
                }
                throw $e;
            }

            return $locked->fresh();
        });

        if ($result->status === self::STATUS_MANUAL_REVIEW) {
            return $result;
        }

        if (! $zohoAlreadyGone) {
            $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);
            $this->attemptZohoVoid($result, $payment, $forceLiveZoho);
        } else {
            $result->zoho_void_status = self::ZOHO_VOIDED;
            $result->zoho_completed_at = $result->zoho_completed_at ?: now();
            $result->save();
        }

        $this->queueInvoiceRefresh(Payment::withoutGlobalScopes()->findOrFail($paymentId));

        return $result->fresh(['payment', 'handover', 'reversal']);
    }

    /**
     * Recovery path used when Zoho void already succeeded and local must be retried.
     */
    public function recoverAfterZohoVoid(CustodyConflict $conflict, User $actor): CustodyConflict
    {
        $conflict->zoho_void_status = self::ZOHO_VOIDED;
        $conflict->status = self::STATUS_CRITICAL_RECOVERY;
        $conflict->critical_alert = true;
        $conflict->save();

        return $this->approve($conflict->fresh(), $actor, forceLiveZoho: true);
    }

    public function retryZohoVoid(CustodyConflict $conflict, bool $forceLiveZoho = false): CustodyConflict
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($conflict->payment_id);
        $this->attemptZohoVoid($conflict, $payment, $forceLiveZoho);
        $this->queueInvoiceRefresh($payment);

        return $conflict->fresh();
    }

    public function reject(CustodyConflict $conflict, User $actor, string $reason): CustodyConflict
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        $locked = CustodyConflict::withoutGlobalScopes()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('Only pending custody reversals can be rejected.');
        }

        $locked->status = self::STATUS_REJECTED;
        $locked->manual_review_reason = $reason;
        $locked->reviewed_by = $actor->id;
        $locked->reviewed_at = now();
        $locked->save();

        if ($locked->reversal_id) {
            PaymentReversal::query()->whereKey($locked->reversal_id)->update([
                'status' => PaymentReversal::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejected_at' => now(),
            ]);
        }

        $this->audit->log('custody_reversal.rejected', $locked, null, ['reason' => $reason], $locked->branch_id);

        return $locked->fresh();
    }

    protected function executeLocalCustodyReversal(
        CustodyConflict $locked,
        Payment $payment,
        BranchCashbox $cashbox,
        User $actor,
        string $amount,
    ): void {
        if ($locked->handover_id) {
            CashHandoverItem::query()
                ->where('handover_id', $locked->handover_id)
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();
            // Intentionally do not mutate handover header amounts.
            CashHandoverRequest::withoutGlobalScopes()
                ->whereKey($locked->handover_id)
                ->lockForUpdate()
                ->first();
        }

        $txn = $this->cashboxes->debit(
            $cashbox,
            $amount,
            'custody_reversal_debit',
            $actor,
            'Custody reversal compensating debit for payment #'.$payment->id
        );

        $this->allocations->reverseForPayment($payment);
        $this->transitions->transition($payment, Payment::STATUS_REVERSED, $actor, 'custody_reversal_approved');
        $payment->reversed_at = now();
        $payment->save();

        if ($payment->receipt_id) {
            $receipt = Receipt::withoutGlobalScopes()->find($payment->receipt_id);
            if ($receipt) {
                $this->receipts->void($receipt);
            }
        }

        if ($locked->reversal_id) {
            PaymentReversal::query()->whereKey($locked->reversal_id)->update([
                'status' => PaymentReversal::STATUS_APPROVED,
                'wallet_reversed' => false,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'approved_at' => now(),
                'zoho_void_attempted' => true,
            ]);
        }

        $recon = PaymentReconciliationRecord::query()->firstOrNew([
            'reconciliation_date' => now()->toDateString(),
            'branch_id' => $payment->branch_id,
        ]);
        $recon->status = 'matched';
        $recon->local_confirmed_count = (int) ($recon->local_confirmed_count ?? 0);
        $recon->zoho_synced_count = (int) ($recon->zoho_synced_count ?? 0);
        $recon->sync_failed_count = (int) ($recon->sync_failed_count ?? 0);
        $recon->reversed_count = (int) ($recon->reversed_count ?? 0) + 1;
        $recon->local_confirmed_amount = $recon->local_confirmed_amount ?? '0.0000';
        $recon->zoho_synced_amount = $recon->zoho_synced_amount ?? '0.0000';
        $recon->variance_amount = $recon->variance_amount ?? '0.0000';
        $details = is_array($recon->details) ? $recon->details : [];
        $events = $details['custody_reversals'] ?? [];
        $events[] = [
            'payment_id' => $payment->id,
            'custody_conflict_id' => $locked->id,
            'handover_id' => $locked->handover_id,
            'cashbox_debit_transaction_id' => $txn->id,
            'at' => now()->toIso8601String(),
        ];
        $details['type'] = 'custody_reversal';
        $details['custody_reversals'] = $events;
        $recon->details = $details;
        $recon->save();

        $locked->status = self::STATUS_APPROVED;
        $locked->cashbox_debit_transaction_id = $txn->id;
        $locked->cashbox_insufficient = false;
        $locked->critical_alert = false;
        $locked->critical_alert_message = null;
        $locked->local_completed_at = now();
        $locked->reviewed_by = $actor->id;
        $locked->reviewed_at = now();
        $locked->approved_amount = $amount;
        $locked->payment_reconciliation_record_id = $recon->id;
        $locked->details = array_merge($locked->details ?? [], [
            'handover_preserved' => true,
            'compensating_debit' => $txn->id,
        ]);
        $locked->save();

        $this->audit->log('custody_reversal.approved_local', $locked, null, [
            'payment_id' => $payment->id,
            'cashbox_txn' => $txn->id,
            'handover_id' => $locked->handover_id,
        ], $locked->branch_id);
    }

    protected function attemptZohoVoid(CustodyConflict $conflict, Payment $payment, bool $forceLiveZoho): void
    {
        if (! filled($payment->zoho_payment_id) || str_starts_with((string) $payment->zoho_payment_id, 'DRYRUN-')) {
            $conflict->zoho_void_status = self::ZOHO_NOT_REQUIRED;
            $conflict->zoho_completed_at = now();
            $conflict->save();

            return;
        }

        $void = $this->zohoSync->voidPayment($payment, $forceLiveZoho);

        if ($void['ok']) {
            $conflict->zoho_void_status = self::ZOHO_VOIDED;
            $conflict->zoho_void_error = null;
            $conflict->zoho_completed_at = now();
            $conflict->save();
            $this->audit->log('custody_reversal.zoho_voided', $conflict, null, [
                'zoho_payment_id' => $void['zoho_payment_id'],
            ], $conflict->branch_id);

            return;
        }

        $conflict->zoho_void_status = self::ZOHO_PENDING;
        $conflict->zoho_void_error = $void['error'];
        $conflict->save();
        $this->audit->log('custody_reversal.zoho_void_pending', $conflict, null, [
            'error' => $void['error'],
        ], $conflict->branch_id);

        RetryCustodyZohoVoidJob::dispatch($conflict->id)->delay(now()->addMinutes(2));
    }

    protected function queueInvoiceRefresh(Payment $payment): void
    {
        $payment->loadMissing('allocations.invoice');
        $ids = $payment->allocations
            ->map(fn ($a) => $a->invoice?->zoho_invoice_id)
            ->filter()
            ->values()
            ->all();
        if ($ids !== []) {
            app(\App\Services\Zoho\ZohoInvoiceSyncService::class)->markAwaitingRefresh($ids);
            RefreshZohoInvoicesJob::dispatch($ids, $payment->id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotDetails(Payment $payment): array
    {
        return [
            'payment_uuid' => $payment->uuid,
            'payment_reference' => $payment->payment_reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'zoho_payment_id' => $payment->zoho_payment_id,
            'handover_status' => $payment->handover_status,
            'cash_handover_request_id' => $payment->cash_handover_request_id,
        ];
    }
}
