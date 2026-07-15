<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_receipt_issued_on_confirm_and_public_verify(): void
    {
        $branch = $this->makeBranch(['code' => 'HER', 'receipt_prefix' => 'HER']);
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod()))
            ->assertCreated()
            ->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $receipt = Receipt::withoutGlobalScopes()->where('payment_id', $confirmed['id'])->firstOrFail();
        $this->assertMatchesRegularExpression('/^HER-\d{4}-\d{6}$/', $receipt->receipt_number);

        $this->getJson('/api/v1/receipts/'.$receipt->uuid)
            ->assertOk()
            ->assertJsonPath('data.receipt_number', $receipt->receipt_number);

        $this->postJson('/api/v1/receipts/'.$receipt->uuid.'/print-log', ['channel' => 'thermal'])
            ->assertCreated();

        $this->getJson('/api/v1/verify-receipt/'.$receipt->verification_token)
            ->assertOk()
            ->assertJsonPath('data.receipt_number', $receipt->receipt_number);

        $this->getJson('/api/v1/receipts/'.$receipt->uuid.'/pdf')->assertOk();
    }
}
