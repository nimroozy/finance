<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentSyncAttempt;
use App\Models\User;
use App\Models\ZohoAccount;
use App\Models\ZohoConnection;
use App\Models\ZohoLocation;
use App\Models\ZohoPaymentMode;
use App\Services\Ownership\BranchPaymentMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class ZohoPaymentSyncTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    private function mapBranchAccount($branch): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        $locationId = $branch->zoho_location_id ?: 'LOC-TEST-'.$branch->id;
        $branch->update(['zoho_location_id' => $locationId]);
        ZohoLocation::query()->updateOrCreate(
            ['zoho_location_id' => $locationId],
            ['organization_id' => 'org-test-1', 'name' => 'Test Loc', 'is_active' => true, 'sync_status' => 'synced']
        );
        ZohoAccount::query()->updateOrCreate(
            ['zoho_account_id' => 'ACC-TEST-'.$branch->id],
            ['organization_id' => 'org-test-1', 'name' => 'Test Cash', 'account_type' => 'cash', 'is_active' => true]
        );
        ZohoPaymentMode::query()->updateOrCreate(
            ['zoho_payment_mode_id' => 'PM-TEST'],
            ['organization_id' => 'org-test-1', 'name' => 'Cash', 'is_active' => true]
        );
        app(BranchPaymentMappingService::class)->upsert(
            $branch->fresh(),
            $admin,
            'ACC-TEST-'.$branch->id,
            'PM-TEST',
            'AFN',
            'TST',
            $locationId
        );
    }

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
        $this->mapBranchAccount($branch);
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

    public function test_force_live_sync_bypasses_dry_run_config(): void
    {
        config([
            'zoho.payments.dry_run' => true,
            'zoho.payments.default_account_id' => 'acct-undeposited',
        ]);

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
            'https://www.zohoapis.com/*/customerpayments*' => Http::sequence()
                ->push(['code' => 0, 'customerpayments' => []], 200)
                ->push([
                    'code' => 0,
                    'payment' => [
                        'payment_id' => 'zoho-live-1',
                        'payment_number' => 'L-1',
                    ],
                ], 201)
                ->push([
                    'code' => 0,
                    'customerpayments' => [
                        [
                            'payment_id' => 'zoho-live-1',
                            'payment_number' => 'L-1',
                            'reference_number' => 'will-rewrite',
                        ],
                    ],
                ], 200),
        ]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'contact-live']);
        $invoice = $this->makeInvoiceForCustomer($customer, ['zoho_invoice_id' => 'inv-live']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $payload = $this->draftPaymentPayload($customer, $collector, $invoice, $this->bankMethod(), '10.0000', [
            'external_reference' => 'BNK-LIVE',
        ]);
        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $payment = Payment::withoutGlobalScopes()->findOrFail($confirmed['id']);
        $this->assertSame(Payment::ZOHO_DRY_RUN, $payment->zoho_sync_status);

        $synced = app(\App\Services\Zoho\ZohoPaymentSyncService::class)->sync($payment, forceLive: true);
        $this->assertSame(Payment::ZOHO_SYNCED, $synced->zoho_sync_status);
        $this->assertSame('zoho-live-1', $synced->zoho_payment_id);

        $again = app(\App\Services\Zoho\ZohoPaymentSyncService::class)->sync($synced->fresh(), forceLive: true);
        $this->assertSame('zoho-live-1', $again->zoho_payment_id);
    }
}
