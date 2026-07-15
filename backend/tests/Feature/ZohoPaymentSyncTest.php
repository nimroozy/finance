<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentSyncAttempt;
use App\Models\ZohoConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class ZohoPaymentSyncTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_dry_run_marks_payment_without_http(): void
    {
        config(['zoho.payments.dry_run' => true]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'contact-1']);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        Http::fake();

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->bankMethod(), '55.0000', [
            'external_reference' => 'BNK-1',
        ]);
        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $payment = Payment::withoutGlobalScopes()->findOrFail($confirmed['id']);
        $this->assertSame(Payment::ZOHO_DRY_RUN, $payment->zoho_sync_status);
        $this->assertNotNull($payment->zoho_payment_id);
        $this->assertDatabaseHas('payment_sync_attempts', [
            'payment_id' => $payment->id,
            'status' => PaymentSyncAttempt::STATUS_DRY_RUN,
            'is_dry_run' => 1,
        ]);

        Http::assertNothingSent();
    }

    public function test_live_sync_posts_to_zoho_with_http_fake(): void
    {
        config(['zoho.payments.dry_run' => false]);

        ZohoConnection::query()->create([
            'data_center' => 'us',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'organization_id' => 'org-test-1',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'status' => 'connected',
            'last_connected_at' => now(),
        ]);

        Http::fake([
            'https://www.zohoapis.com/*' => Http::response([
                'code' => 0,
                'payment' => [
                    'payment_id' => 'zoho-pay-99',
                    'payment_number' => '999',
                ],
            ], 201),
        ]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'contact-77']);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->bankMethod(), '33.0000', [
            'external_reference' => 'BNK-2',
        ]);
        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $payment = Payment::withoutGlobalScopes()->findOrFail($confirmed['id']);
        $this->assertSame(Payment::ZOHO_SYNCED, $payment->zoho_sync_status);
        $this->assertSame('zoho-pay-99', $payment->zoho_payment_id);
        $this->assertSame(Payment::STATUS_SYNCED, $payment->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'customerpayments'));
    }
}
