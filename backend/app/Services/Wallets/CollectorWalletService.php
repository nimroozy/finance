<?php

namespace App\Services\Wallets;

use App\Models\CollectorWallet;
use App\Models\CollectorWalletTransaction;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\CashHandoverRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollectorWalletService
{
    public function getOrCreate(int $collectorId, int $branchId, string $currency = 'AFN'): CollectorWallet
    {
        return CollectorWallet::withoutGlobalScopes()->firstOrCreate(
            [
                'collector_id' => $collectorId,
                'branch_id' => $branchId,
                'currency' => strtoupper($currency),
            ],
            [
                'balance' => Money::normalize('0'),
                'pending_handover_balance' => Money::normalize('0'),
            ]
        );
    }

    public function creditFromPayment(Payment $payment, ?User $actor = null): CollectorWalletTransaction
    {
        return DB::transaction(function () use ($payment, $actor) {
            $wallet = $this->getOrCreate(
                (int) $payment->collector_id,
                (int) $payment->branch_id,
                $payment->currency
            );

            /** @var CollectorWallet $locked */
            $locked = CollectorWallet::withoutGlobalScopes()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CollectorWalletTransaction::query()
                ->where('payment_id', $payment->id)
                ->where('type', CollectorWalletTransaction::TYPE_PAYMENT_CREDIT)
                ->exists();

            if ($existing) {
                throw new RuntimeException('Wallet already credited for this payment.');
            }

            $before = Money::normalize($locked->balance);
            $amount = Money::normalize($payment->amount);
            $after = Money::add($before, $amount);

            $locked->balance = $after;
            $locked->pending_handover_balance = Money::add($locked->pending_handover_balance, $amount);
            $locked->last_transaction_at = now();
            $locked->save();

            return CollectorWalletTransaction::create([
                'collector_wallet_id' => $locked->id,
                'collector_id' => $locked->collector_id,
                'branch_id' => $locked->branch_id,
                'payment_id' => $payment->id,
                'type' => CollectorWalletTransaction::TYPE_PAYMENT_CREDIT,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'currency' => $locked->currency,
                'reference' => $payment->payment_reference,
                'notes' => 'Cash payment credit',
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);
        });
    }

    public function debitFromReversal(Payment $payment, PaymentReversal $reversal, ?User $actor = null): CollectorWalletTransaction
    {
        return DB::transaction(function () use ($payment, $reversal, $actor) {
            $wallet = $this->getOrCreate(
                (int) $payment->collector_id,
                (int) $payment->branch_id,
                $payment->currency
            );

            /** @var CollectorWallet $locked */
            $locked = CollectorWallet::withoutGlobalScopes()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = Money::normalize($locked->balance);
            $amount = Money::normalize($payment->amount);
            $after = Money::sub($before, $amount);

            if (Money::isNegative($after)) {
                throw new RuntimeException('Insufficient wallet balance to reverse payment.');
            }

            $locked->balance = $after;
            $locked->pending_handover_balance = Money::max(
                '0',
                Money::sub($locked->pending_handover_balance, $amount)
            );
            $locked->last_transaction_at = now();
            $locked->save();

            return CollectorWalletTransaction::create([
                'collector_wallet_id' => $locked->id,
                'collector_id' => $locked->collector_id,
                'branch_id' => $locked->branch_id,
                'payment_id' => $payment->id,
                'reversal_id' => $reversal->id,
                'type' => CollectorWalletTransaction::TYPE_REVERSAL_DEBIT,
                'amount' => Money::mul($amount, '-1'),
                'balance_before' => $before,
                'balance_after' => $after,
                'currency' => $locked->currency,
                'reference' => $payment->payment_reference,
                'notes' => 'Payment reversal debit',
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);
        });
    }

    public function debitForCashHandover(CashHandoverRequest $handover, string $amount, ?User $actor = null): CollectorWalletTransaction
    {
        return DB::transaction(function () use ($handover, $amount, $actor) {
            $wallet = $this->getOrCreate($handover->collector_id, $handover->branch_id, $handover->currency);
            $locked = CollectorWallet::withoutGlobalScopes()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $amount = Money::normalize($amount);
            $before = Money::normalize($locked->balance);
            $after = Money::sub($before, $amount);

            if (Money::isNegative($after)) {
                throw new RuntimeException('Insufficient wallet balance for cash handover.');
            }

            $locked->balance = $after;
            $locked->pending_handover_balance = Money::max('0', Money::sub($locked->pending_handover_balance, $amount));
            $locked->last_transaction_at = now();
            $locked->save();

            return CollectorWalletTransaction::create([
                'collector_wallet_id' => $locked->id, 'collector_id' => $locked->collector_id,
                'branch_id' => $locked->branch_id, 'handover_id' => $handover->id,
                'type' => CollectorWalletTransaction::TYPE_CASH_HANDOVER, 'amount' => Money::mul($amount, '-1'),
                'balance_before' => $before, 'balance_after' => $after, 'currency' => $locked->currency,
                'reference' => $handover->handover_number, 'notes' => 'Cash handover debit',
                'created_by' => $actor?->id, 'created_at' => now(),
            ]);
        });
    }
}
