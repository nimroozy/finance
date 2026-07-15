<?php

namespace Tests\Feature;

use App\Models\CollectorWallet;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentReversalTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_reversal_request_and_approve_frees_allocation_and_wallet(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '200.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '80.0000'))
            ->assertCreated()
            ->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $reversal = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/reversal-request', [
            'reason' => 'Entered wrong amount',
        ])->assertCreated()->json('data');

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/reversals/'.$reversal['id'].'/approve')->assertOk();

        $payment = Payment::withoutGlobalScopes()->findOrFail($draft['id']);
        $this->assertSame(Payment::STATUS_REVERSED, $payment->status);
        $this->assertSame(PaymentAllocation::STATUS_REVERSED, PaymentAllocation::where('payment_id', $payment->id)->value('status'));

        $wallet = CollectorWallet::withoutGlobalScopes()->where('collector_id', $collector->id)->first();
        $this->assertSame('0.0000', (string) $wallet->balance);

        // Soft-delete not used for confirmed payments.
        $this->assertNull($payment->deleted_at);
    }

    public function test_manager_can_reject_reversal(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod()))
            ->assertCreated()
            ->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();
        $reversal = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/reversal-request', [
            'reason' => 'Need reversal',
        ])->assertCreated()->json('data');

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/reversals/'.$reversal['id'].'/reject', [
            'reason' => 'Insufficient evidence',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
    }
}
