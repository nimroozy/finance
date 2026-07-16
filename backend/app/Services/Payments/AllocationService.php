<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Effective available balance for an invoice:
 *   zoho_balance (invoices.balance) − sum(confirmed local allocations not reversed)
 * Confirmed local means payment is in CONFIRMED_STATUSES and allocation status = active.
 * This reserves headroom for payments confirmed locally but not yet mirrored in Zoho.
 */
class AllocationService
{
    public function effectiveAvailableBalance(Invoice $invoice): string
    {
        $zohoBalance = Money::normalize($invoice->balance ?? '0');
        $reserved = $this->reservedLocalAmount((int) $invoice->id);

        return Money::max('0', Money::sub($zohoBalance, $reserved));
    }

    public function reservedLocalAmount(int $invoiceId): string
    {
        $sum = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('pa.invoice_id', $invoiceId)
            ->where('pa.status', PaymentAllocation::STATUS_ACTIVE)
            ->whereIn('p.status', Payment::CONFIRMED_STATUSES)
            ->whereNull('p.deleted_at')
            ->sum('pa.amount');

        return Money::normalize($sum ?? '0');
    }

    /**
     * @param  list<array{invoice_id:int,amount:string|int|float}>  $allocations
     * @return list<array{invoice:Invoice,amount:string,available:string}>
     */
    public function validateAllocations(int $customerId, string $paymentAmount, array $allocations, ?int $excludePaymentId = null): array
    {
        if ($allocations === []) {
            throw new InvalidArgumentException('At least one allocation is required.');
        }

        $normalized = [];
        $total = Money::normalize('0');
        $seen = [];

        foreach ($allocations as $row) {
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            $amount = Money::normalize($row['amount'] ?? '0');

            if ($invoiceId <= 0) {
                throw new InvalidArgumentException('Allocation invoice_id is required.');
            }

            if (isset($seen[$invoiceId])) {
                throw new InvalidArgumentException("Duplicate allocation for invoice [{$invoiceId}].");
            }
            $seen[$invoiceId] = true;

            if (! Money::isPositive($amount)) {
                throw new InvalidArgumentException('Each allocation amount must be greater than zero.');
            }

            /** @var Invoice|null $invoice */
            $invoice = Invoice::query()->whereKey($invoiceId)->first();
            if (! $invoice) {
                throw new InvalidArgumentException("Invoice [{$invoiceId}] not found.");
            }

            if ((int) $invoice->customer_id !== $customerId) {
                throw new InvalidArgumentException("Invoice [{$invoiceId}] does not belong to customer.");
            }

            $available = $this->effectiveAvailableBalance($invoice);

            // When confirming an existing draft, its unconfirmed allocations do not reserve yet.
            // excludePaymentId is unused for reservation (drafts are not in CONFIRMED_STATUSES).
            unset($excludePaymentId);

            if (Money::compare($amount, $available) === 1) {
                throw new InvalidArgumentException(
                    "Allocation {$amount} exceeds effective available balance {$available} on invoice [{$invoiceId}]."
                );
            }

            $normalized[] = [
                'invoice' => $invoice,
                'amount' => $amount,
                'available' => $available,
            ];
            $total = Money::add($total, $amount);
        }

        if (! Money::equals($total, $paymentAmount)) {
            throw new InvalidArgumentException(
                "Allocations sum [{$total}] must equal payment amount [{$paymentAmount}]."
            );
        }

        return $normalized;
    }

    /**
     * @param  list<array{invoice:Invoice,amount:string,available:string}>  $validated
     */
    public function persist(Payment $payment, array $validated): Collection
    {
        $payment->allocations()->delete();

        $created = collect();
        foreach ($validated as $row) {
            /** @var Invoice $invoice */
            $invoice = $row['invoice'];
            $amount = $row['amount'];
            $before = Money::normalize($invoice->balance ?? '0');
            $after = Money::sub($before, $amount);

            $created->push(PaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'status' => PaymentAllocation::STATUS_ACTIVE,
                'invoice_balance_before' => $before,
                'invoice_balance_after' => $after,
            ]));
        }

        return $created;
    }

    public function reverseForPayment(Payment $payment): void
    {
        PaymentAllocation::query()
            ->where('payment_id', $payment->id)
            ->where('status', PaymentAllocation::STATUS_ACTIVE)
            ->update(['status' => PaymentAllocation::STATUS_REVERSED]);
    }
}
