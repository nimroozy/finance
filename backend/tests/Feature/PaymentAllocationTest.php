<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentAllocationTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_allocations_must_sum_to_payment_amount(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '500.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '100.0000');
        $payload['allocations'][0]['amount'] = '50.0000';

        $this->postJson('/api/v1/payments/draft', $payload)->assertStatus(422);
    }

    public function test_cannot_allocate_more_than_effective_available_balance(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '100.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);

        $first = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '60.0000');
        $draft = $this->postJson('/api/v1/payments/draft', $first)->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $second = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '50.0000');
        $this->postJson('/api/v1/payments/draft', $second)->assertStatus(422);
    }

    public function test_split_allocations_across_invoices(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $inv1 = $this->makeInvoiceForCustomer($customer, ['balance' => '80.0000']);
        $inv2 = $this->makeInvoiceForCustomer($customer, ['balance' => '70.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $inv1, $this->cashMethod(), '100.0000');
        $payload['allocations'] = [
            ['invoice_id' => $inv1->id, 'amount' => '60.0000'],
            ['invoice_id' => $inv2->id, 'amount' => '40.0000'],
        ];

        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $this->assertCount(2, PaymentAllocation::where('payment_id', $confirmed['id'])->get());
        $this->assertTrue(Money::equals(
            PaymentAllocation::where('payment_id', $confirmed['id'])->sum('amount'),
            '100.0000'
        ));
        $this->assertSame(Payment::STATUS_SETTLED_PENDING_HANDOVER, $confirmed['status']);
    }
}
