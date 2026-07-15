<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Receipts\ReceiptService;
use App\Services\Wallets\CollectorWalletService;
use App\Services\Zoho\ZohoPaymentSyncService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentReversalService
{
    public function __construct(
        protected PaymentStatusTransitionService $transitions,
        protected AllocationService $allocations,
        protected CollectorWalletService $wallets,
        protected ReceiptService $receipts,
        protected ZohoPaymentSyncService $zohoSync,
        protected AuditLogger $audit,
    ) {}

    public function request(Payment $payment, User $actor, string $reason): PaymentReversal
    {
        if (! $payment->isConfirmed()) {
            throw new InvalidArgumentException('Only confirmed payments can be reversed.');
        }

        if ($payment->isReversed()) {
            throw new InvalidArgumentException('Payment is already reversed.');
        }

        $pending = PaymentReversal::query()
            ->where('payment_id', $payment->id)
            ->where('status', PaymentReversal::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw new InvalidArgumentException('A pending reversal already exists for this payment.');
        }

        $reversal = PaymentReversal::create([
            'payment_id' => $payment->id,
            'requested_by' => $actor->id,
            'branch_id' => $payment->branch_id,
            'status' => PaymentReversal::STATUS_PENDING,
            'reason' => $reason,
        ]);

        $this->audit->log('payment.reversal_requested', $reversal, null, $reversal->toArray(), $payment->branch_id);

        return $reversal;
    }

    public function approve(PaymentReversal $reversal, User $actor, bool $forceLiveZoho = false): PaymentReversal
    {
        return DB::transaction(function () use ($reversal, $actor, $forceLiveZoho) {
            /** @var PaymentReversal $locked */
            $locked = PaymentReversal::query()->whereKey($reversal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentReversal::STATUS_PENDING) {
                throw new InvalidArgumentException('Reversal is not pending.');
            }

            /** @var Payment $payment */
            $payment = Payment::withoutGlobalScopes()
                ->whereKey($locked->payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->isReversed()) {
                throw new InvalidArgumentException('Payment is already reversed.');
            }

            // Attempt Zoho void first for live synced payments (never fake success).
            $void = $this->zohoSync->voidPayment($payment, $forceLiveZoho);
            $locked->zoho_void_attempted = true;
            if (! $void['ok']) {
                $locked->zoho_void_error = $void['error'];
                $locked->reviewed_by = $actor->id;
                $locked->reviewed_at = now();
                $locked->save();

                $this->audit->log('payment.reversal_manual_review', $locked, null, [
                    'error' => $void['error'],
                    'zoho_payment_id' => $void['zoho_payment_id'],
                ], $locked->branch_id);

                throw new InvalidArgumentException(
                    'Zoho payment could not be reversed automatically. Marked for manual review: '.($void['error'] ?? 'unknown')
                );
            }

            // Never delete confirmed payments — mark reversed and reverse allocations/wallet.
            $this->allocations->reverseForPayment($payment);
            $this->transitions->transition($payment, Payment::STATUS_REVERSED, $actor, 'reversal_approved');

            $payment->reversed_at = now();
            $payment->save();

            $method = $payment->method()->first();
            if ($method?->affects_cash_wallet) {
                $this->wallets->debitFromReversal($payment, $locked, $actor);
                $locked->wallet_reversed = true;
            }

            if ($payment->receipt_id) {
                $receipt = Receipt::withoutGlobalScopes()->find($payment->receipt_id);
                if ($receipt) {
                    $this->receipts->void($receipt);
                }
            }

            $locked->status = PaymentReversal::STATUS_APPROVED;
            $locked->zoho_void_error = null;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->approved_at = now();
            $locked->save();

            $this->audit->log('payment.reversal_approved', $locked, null, $locked->toArray(), $locked->branch_id);

            return $locked->fresh(['payment']);
        });
    }

    public function reject(PaymentReversal $reversal, User $actor, string $reason): PaymentReversal
    {
        if ($reversal->status !== PaymentReversal::STATUS_PENDING) {
            throw new InvalidArgumentException('Reversal is not pending.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        $reversal->status = PaymentReversal::STATUS_REJECTED;
        $reversal->rejection_reason = $reason;
        $reversal->reviewed_by = $actor->id;
        $reversal->reviewed_at = now();
        $reversal->rejected_at = now();
        $reversal->save();

        $this->audit->log('payment.reversal_rejected', $reversal, null, $reversal->toArray(), $reversal->branch_id);

        return $reversal;
    }
}
