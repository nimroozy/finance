<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\ZohoAccount;
use App\Models\ZohoLocation;
use App\Models\ZohoPaymentMode;
use App\Services\Ownership\BranchPaymentMappingService;
use App\Services\Zoho\ZohoPaymentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class BranchPaymentMappingAndHandoverZohoGuardTest extends TestCase
{
    use CreatesPaymentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_live_payment_blocked_when_mapping_incomplete(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'ZC-1']);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        config(['zoho.payments.dry_run' => true]);
        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod()))
            ->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $payment = Payment::withoutGlobalScopes()->where('uuid', $draft['uuid'])->firstOrFail();
        config(['zoho.payments.dry_run' => false]);
        Http::fake();
        $this->expectException(\InvalidArgumentException::class);
        app(ZohoPaymentSyncService::class)->sync($payment, forceLive: true);
    }

    public function test_confirmed_payment_uses_branch_account_snapshot(): void
    {
        $branch = $this->makeBranch(['zoho_location_id' => 'LOC-X']);
        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        $admin->branches()->attach($branch->id);
        ZohoLocation::query()->create(['organization_id' => 'o', 'zoho_location_id' => 'LOC-X', 'name' => 'Test', 'is_active' => true, 'sync_status' => 'synced']);
        ZohoAccount::query()->create(['organization_id' => 'o', 'zoho_account_id' => 'ACC-X', 'name' => 'Branch Cash', 'account_type' => 'cash', 'is_active' => true]);
        ZohoPaymentMode::query()->create(['organization_id' => 'o', 'zoho_payment_mode_id' => 'PM-X', 'name' => 'Cash', 'is_active' => true]);
        app(BranchPaymentMappingService::class)->upsert($branch, $admin, 'ACC-X', 'PM-X', 'AFN', 'TST', 'LOC-X');

        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'ZC-2']);
        $invoice = $this->makeInvoiceForCustomer($customer, ['zoho_invoice_id' => 'INV-X']);
        $this->makeActiveAssignment($admin, $customer, $collector);

        config(['zoho.payments.dry_run' => true]);
        $this->actingAsUser($collectorUser);
        $created = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod()))
            ->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$created['uuid'].'/confirm')->assertOk();

        $payment = Payment::withoutGlobalScopes()->where('uuid', $created['uuid'])->firstOrFail();
        app(ZohoPaymentSyncService::class)->sync($payment);
        $payment->refresh();
        $this->assertSame('ACC-X', $payment->zoho_account_id_snapshot);
        $this->assertSame('Branch Cash', $payment->zoho_account_name_snapshot);
        $this->assertNotEmpty($payment->zoho_payment_id);
    }

    public function test_handover_approve_does_not_post_zoho_customer_payment(): void
    {
        Http::fake();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collector);

        config(['zoho.payments.dry_run' => true]);
        $this->actingAsUser($collectorUser);
        $created = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod(), '75.0000'))
            ->assertCreated()->json('data');
        $payment = $this->postJson('/api/v1/payments/'.$created['uuid'].'/confirm')->assertOk()->json('data');
        $beforeBalance = (string) Invoice::withoutGlobalScopes()->find($invoice->id)->balance;

        $handover = $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$payment['id']],
            'declared_amount' => '75.0000',
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/submit')->assertOk();
        $this->actingAsUser($manager);
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/approve', [
            'counted_amount' => '75.0000',
            'approved_amount' => '75.0000',
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertSame($beforeBalance, (string) Invoice::withoutGlobalScopes()->find($invoice->id)->balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_handover.completed_without_zoho_payment']);
    }
}
