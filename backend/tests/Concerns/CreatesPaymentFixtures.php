<?php

namespace Tests\Concerns;

use App\Models\CustomerAssignment;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Str;

trait CreatesPaymentFixtures
{
    use CreatesCollectionFixtures;

    protected function cashMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('code', 'cash')->firstOrFail();
    }

    protected function bankMethod(): PaymentMethod
    {
        return PaymentMethod::query()->where('code', 'bank_transfer')->firstOrFail();
    }

    protected function makeInvoiceForCustomer($customer, array $attributes = []): Invoice
    {
        return Invoice::withoutGlobalScopes()->create(array_merge([
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'ZOHO-INV-'.Str::upper(Str::random(8)),
            'invoice_number' => 'INV-'.Str::upper(Str::random(6)),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '1000.0000',
            'amount_paid' => '0.0000',
            'credits_applied' => '0.0000',
            'balance' => '1000.0000',
            'sync_status' => 'synced',
        ], $attributes));
    }

    protected function makeActiveAssignment($manager, $customer, $collector): CustomerAssignment
    {
        $this->actingAsUser($manager);
        $payload = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertCreated()->json('data.assignment');

        return CustomerAssignment::withoutGlobalScopes()->findOrFail($payload['id']);
    }

    protected function draftPaymentPayload($customer, $collector, $invoice, $method, string $amount = '100.0000', array $extra = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'payment_method_id' => $method->id,
            'amount' => Money::normalize($amount),
            'currency' => 'AFN',
            'allocations' => [
                ['invoice_id' => $invoice->id, 'amount' => Money::normalize($amount)],
            ],
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }
}
