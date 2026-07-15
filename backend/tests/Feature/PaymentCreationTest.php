<?php

namespace Tests\Feature;

use App\Models\CollectorWallet;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentCreationTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_collector_can_preview_and_confirm_cash_payment(): void
    {
        $branch = $this->makeBranch(['receipt_prefix' => 'KBL']);
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $method = $this->cashMethod();
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $method, '250.0000');

        $this->postJson('/api/v1/payments/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.amount', '250.0000');

        $draft = $this->postJson('/api/v1/payments/draft', $payload)
            ->assertCreated()
            ->json('data');

        $this->assertSame(Payment::STATUS_DRAFT, $draft['status']);

        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm', [
            'idempotency_key' => 'confirm-1',
        ])->assertOk()->json('data');

        $this->assertSame(Payment::STATUS_SETTLED_PENDING_HANDOVER, $confirmed['status']);
        $this->assertNotNull($confirmed['payment_reference']);
        $this->assertNotNull($confirmed['receipt_id']);

        $this->assertDatabaseHas('collector_wallets', [
            'collector_id' => $collector->id,
            'branch_id' => $branch->id,
        ]);

        $wallet = CollectorWallet::withoutGlobalScopes()->where('collector_id', $collector->id)->first();
        $this->assertSame('250.0000', (string) $wallet->balance);

        $receipt = Receipt::withoutGlobalScopes()->where('payment_id', $confirmed['id'])->first();
        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('KBL-'.now()->year.'-', $receipt->receipt_number);
    }

    public function test_rejects_inactive_customer(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $customer->update(['status' => 'inactive']);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod());

        $this->postJson('/api/v1/payments/draft', $payload)->assertStatus(422);
    }
}
