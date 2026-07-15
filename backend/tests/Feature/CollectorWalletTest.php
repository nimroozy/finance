<?php

namespace Tests\Feature;

use App\Models\CollectorWallet;
use App\Models\CollectorWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class CollectorWalletTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_cash_confirm_credits_wallet_once(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '75.5000'))
            ->assertCreated()
            ->json('data');

        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $wallet = CollectorWallet::withoutGlobalScopes()->where('collector_id', $collector->id)->first();
        $this->assertSame('75.5000', (string) $wallet->balance);
        $this->assertSame(1, CollectorWalletTransaction::where('payment_id', $draft['id'])->count());

        $this->getJson('/api/v1/collector/wallet?branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('data.balance', '75.5000');
    }

    public function test_bank_transfer_does_not_credit_wallet(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->bankMethod(), '40.0000', [
            'external_reference' => 'TRX-001',
        ]);
        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $this->assertSame(0, CollectorWallet::withoutGlobalScopes()->where('collector_id', $collector->id)->count());
    }
}
