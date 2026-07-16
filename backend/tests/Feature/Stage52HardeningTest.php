<?php

namespace Tests\Feature;

use App\Jobs\RefreshZohoInvoicesJob;
use App\Models\Branch;
use App\Models\BranchCashbox;
use App\Models\BranchCashboxTransaction;
use App\Models\BranchZohoAccountMapping;
use App\Models\CashHandoverRequest;
use App\Models\CustodyConflict;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\ZohoAccount;
use App\Models\ZohoConnection;
use App\Models\ZohoLocation;
use App\Models\ZohoPaymentMode;
use App\Services\Cash\CustodyAwareReversalService;
use App\Services\Invoices\InvoiceBalanceService;
use App\Services\Ownership\BranchPaymentMappingService;
use App\Services\Zoho\ZohoPaymentSyncService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class Stage52HardeningTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    protected function ensureZohoConnection(): void
    {
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
    }

    protected function seedNimruzMapping(Branch $branch): BranchZohoAccountMapping
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        $locationId = $branch->zoho_location_id ?: 'LOC-NMRZ';
        $branch->update(['zoho_location_id' => $locationId]);
        ZohoLocation::query()->updateOrCreate(
            ['zoho_location_id' => $locationId],
            ['organization_id' => 'org-test-1', 'name' => 'Nimruz', 'is_active' => true, 'sync_status' => 'synced']
        );
        ZohoLocation::query()->updateOrCreate(
            ['zoho_location_id' => 'LOC-KBL'],
            ['organization_id' => 'org-test-1', 'name' => 'Kabul', 'is_active' => true, 'sync_status' => 'synced']
        );
        ZohoAccount::query()->updateOrCreate(
            ['zoho_account_id' => '303766000001253740'],
            ['organization_id' => 'org-test-1', 'name' => 'Nimruz Cash AFN', 'account_type' => 'bank', 'is_active' => true]
        );
        ZohoPaymentMode::query()->updateOrCreate(
            ['zoho_payment_mode_id' => '303766000000013003'],
            ['organization_id' => 'org-test-1', 'name' => 'Cash', 'is_active' => true]
        );

        return app(BranchPaymentMappingService::class)->upsert(
            $branch->fresh(),
            $admin,
            '303766000001253740',
            '303766000000013003',
            'AFN',
            'NMZ',
            $locationId
        );
    }

    public function test_api_returns_effective_balance_when_raw_zoho_balance_stale(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '100.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '100.0000'
        ))->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $invoice->refresh();
        $this->assertSame('100.0000', Money::normalize((string) $invoice->balance));

        $this->actingAsUser($manager);
        $payload = $this->getJson('/api/v1/invoices/'.$invoice->id)->assertOk()->json('data');
        $this->assertSame('100.0000', $payload['zoho_synced_balance']);
        $this->assertSame('100.0000', $payload['local_pending_allocation']);
        $this->assertSame('0.0000', $payload['effective_balance']);
        $this->assertTrue($payload['awaiting_zoho_refresh']);
    }

    public function test_second_payment_blocked_when_effective_balance_zero(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '50.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '50.0000'
        ))->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '50.0000'
        ))->assertStatus(422);
    }

    public function test_successful_zoho_sync_queues_targeted_invoice_refresh(): void
    {
        Queue::fake();
        config(['zoho.payments.dry_run' => false, 'zoho.payments.default_account_id' => '303766000001253740']);
        $this->ensureZohoConnection();
        Http::fake([
            '*/customerpayments*' => Http::response(['payment' => ['payment_id' => 'ZP-1', 'payment_number' => 'P-1']], 201),
        ]);

        $branch = $this->makeBranch(['name_en' => 'Nimruz', 'name_fa' => 'نیمروز', 'zoho_location_id' => 'LOC-NMRZ']);
        $this->seedNimruzMapping($branch);
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['zoho_contact_id' => 'ZC-1']);
        $invoice = $this->makeInvoiceForCustomer($customer, [
            'balance' => '10.0000',
            'zoho_invoice_id' => 'ZI-10',
            'zoho_location_id' => 'LOC-NMRZ',
        ]);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '10.0000'
        ))->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $payment = Payment::withoutGlobalScopes()->findOrFail($confirmed['id']);
        app(ZohoPaymentSyncService::class)->sync($payment, forceLive: true);

        Queue::assertPushed(RefreshZohoInvoicesJob::class, function (RefreshZohoInvoicesJob $job) {
            return in_array('ZI-10', $job->zohoInvoiceIds, true);
        });
    }

    public function test_refresh_failure_preserves_effective_balance(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, [
            'balance' => '25.0000',
            'zoho_invoice_id' => 'ZI-FAIL',
        ]);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '25.0000'
        ))->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        app(\App\Services\Zoho\ZohoInvoiceSyncService::class)->markRefreshFailed(['ZI-FAIL'], 'zoho down');
        $invoice->refresh();
        $desc = app(InvoiceBalanceService::class)->describe($invoice);
        $this->assertSame('0.0000', $desc['effective_balance']);
        $this->assertSame('refresh_failed', $desc['balance_sync_status']);
        $this->assertTrue($desc['awaiting_zoho_refresh']);
    }

    public function test_kabul_invoice_cannot_use_nimruz_account_before_http(): void
    {
        Queue::fake();
        Http::fake();
        config(['zoho.payments.dry_run' => true]);
        $this->ensureZohoConnection();

        $nimruz = $this->makeBranch(['code' => '01', 'name_en' => 'Nimruz', 'name_fa' => 'نیمروز', 'zoho_location_id' => 'LOC-NMRZ']);
        $this->makeBranch(['code' => 'KABUL', 'name_en' => 'Kabul', 'name_fa' => 'کابل', 'zoho_location_id' => 'LOC-KBL']);
        $this->seedNimruzMapping($nimruz);

        $manager = $this->makeManager($nimruz);
        [$collectorUser, $collector] = $this->makeCollectorUser($nimruz);
        $customer = $this->makeCustomer($nimruz, ['zoho_contact_id' => 'ZC-2']);
        $invoice = $this->makeInvoiceForCustomer($customer, [
            'balance' => '10.0000',
            'zoho_location_id' => 'LOC-KBL',
            'zoho_invoice_id' => 'ZI-KBL',
        ]);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '10.0000'
        ))->assertCreated()->json('data');
        $confirmed = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');
        $payment = Payment::withoutGlobalScopes()->findOrFail($confirmed['id']);

        config(['zoho.payments.dry_run' => false]);
        try {
            app(ZohoPaymentSyncService::class)->sync($payment, forceLive: true);
            $this->fail('Expected location mismatch exception');
        } catch (\App\Support\LocalizedInvalidArgumentException $e) {
            $this->assertStringContainsString('Kabul', $e->getMessage());
            $this->assertStringContainsString('کابل', $e->messageFa);
        }

        Http::assertNothingSent();
    }

    public function test_custody_reversal_request_and_unauthorized_denied(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '40.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $payment = $this->confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, '40.0000', $branch);

        $this->actingAsUser($collectorUser);
        $reversal = $this->postJson('/api/v1/payments/'.$payment->uuid.'/reversal-request', [
            'reason' => 'Test custody reversal',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('custody_conflicts', [
            'payment_id' => $payment->id,
            'reversal_id' => $reversal['id'],
            'status' => 'pending_review',
        ]);

        $this->actingAsUser($collectorUser);
        $conflictId = CustodyConflict::withoutGlobalScopes()->where('payment_id', $payment->id)->value('id');
        $this->postJson('/api/v1/custody-reversals/'.$conflictId.'/approve')->assertForbidden();
    }

    public function test_approved_custody_reversal_debits_cashbox_and_preserves_handover(): void
    {
        config(['zoho.payments.dry_run' => true]);
        Http::fake([
            '*/customerpayments/*' => Http::response([], 200),
        ]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '55.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $payment = $this->confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, '55.0000', $branch);
        $handoverId = $payment->cash_handover_request_id;
        $handoverBefore = CashHandoverRequest::withoutGlobalScopes()->findOrFail($handoverId);

        $this->actingAsUser($collectorUser);
        $this->postJson('/api/v1/payments/'.$payment->uuid.'/reversal-request', [
            'reason' => 'Need custody reverse',
        ])->assertCreated();

        $conflict = CustodyConflict::withoutGlobalScopes()->where('payment_id', $payment->id)->firstOrFail();
        $this->actingAsUser($manager);
        $this->postJson('/api/v1/custody-reversals/'.$conflict->id.'/approve')->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REVERSED, $payment->status);
        $this->assertSame(PaymentAllocation::STATUS_REVERSED, PaymentAllocation::where('payment_id', $payment->id)->value('status'));
        $this->assertSame('0.0000', Money::normalize((string) BranchCashbox::withoutGlobalScopes()->where('branch_id', $branch->id)->value('balance')));
        $this->assertTrue(BranchCashboxTransaction::withoutGlobalScopes()->where('type', 'custody_reversal_debit')->exists());

        $handoverAfter = CashHandoverRequest::withoutGlobalScopes()->findOrFail($handoverId);
        $this->assertSame($handoverBefore->status, $handoverAfter->status);
        $this->assertSame(Money::normalize((string) $handoverBefore->approved_amount), Money::normalize((string) $handoverAfter->approved_amount));
        $this->assertSame($handoverBefore->handover_number, $handoverAfter->handover_number);

        $effective = app(InvoiceBalanceService::class)->describe($invoice->fresh());
        $this->assertSame('55.0000', $effective['effective_balance']);
    }

    public function test_insufficient_cashbox_moves_to_manual_review_without_zoho_void(): void
    {
        config(['zoho.payments.dry_run' => true]);
        Http::fake();

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '30.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $payment = $this->confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, '30.0000', $branch);
        $payment->zoho_payment_id = 'ZP-REAL-1';
        $payment->zoho_sync_status = Payment::ZOHO_SYNCED;
        $payment->save();

        BranchCashbox::withoutGlobalScopes()->where('branch_id', $branch->id)->update(['balance' => '5.0000']);

        $this->actingAsUser($collectorUser);
        $this->postJson('/api/v1/payments/'.$payment->uuid.'/reversal-request', [
            'reason' => 'Insufficient cashbox case',
        ])->assertCreated();

        $conflict = CustodyConflict::withoutGlobalScopes()->where('payment_id', $payment->id)->firstOrFail();
        $this->actingAsUser($manager);
        $result = $this->postJson('/api/v1/custody-reversals/'.$conflict->id.'/approve')->assertOk()->json('data');

        $this->assertSame('manual_review', $result['conflict']['status']);
        $this->assertTrue((bool) $result['conflict']['cashbox_insufficient']);
        $this->assertFalse($payment->fresh()->isReversed());
        Http::assertNothingSent();
    }

    public function test_local_success_zoho_failure_marks_pending_retry(): void
    {
        Queue::fake();
        config(['zoho.payments.dry_run' => true]);
        $this->ensureZohoConnection();
        Http::fake([
            '*/customerpayments/*' => Http::response(['message' => 'cannot void'], 500),
        ]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '12.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $payment = $this->confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, '12.0000', $branch);
        $payment->zoho_payment_id = 'ZP-REAL-2';
        $payment->zoho_sync_status = Payment::ZOHO_SYNCED;
        $payment->save();

        $this->actingAsUser($collectorUser);
        $this->postJson('/api/v1/payments/'.$payment->uuid.'/reversal-request', [
            'reason' => 'Zoho fail after local',
        ])->assertCreated();

        $conflict = CustodyConflict::withoutGlobalScopes()->where('payment_id', $payment->id)->firstOrFail();
        $this->actingAsUser($manager);
        app(CustodyAwareReversalService::class)->approve($conflict, $manager, forceLiveZoho: true);

        $conflict->refresh();
        $this->assertSame(CustodyAwareReversalService::STATUS_APPROVED, $conflict->status);
        $this->assertSame(CustodyAwareReversalService::ZOHO_PENDING, $conflict->zoho_void_status);
        $this->assertTrue($payment->fresh()->isReversed());
    }

    public function test_zoho_void_idempotent_when_already_absent(): void
    {
        config(['zoho.payments.dry_run' => false]);
        $this->ensureZohoConnection();
        Http::fake([
            '*/customerpayments/*' => Http::response(['code' => 1002, 'message' => 'Resource not found'], 404),
        ]);

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $payment = Payment::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'created_by' => $manager->id,
            'branch_id' => $branch->id,
            'payment_method_id' => $this->cashMethod()->id,
            'currency' => 'AFN',
            'amount' => '10.0000',
            'status' => Payment::STATUS_SETTLED_PENDING_HANDOVER,
            'handover_status' => 'handed_over',
            'zoho_sync_status' => Payment::ZOHO_SYNCED,
            'zoho_payment_id' => 'ZP-GONE',
            'payment_reference' => 'REF-GONE',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $result = app(ZohoPaymentSyncService::class)->voidPayment($payment, forceLive: true);
        $this->assertTrue($result['ok']);
        Http::assertSentCount(1);
    }

    public function test_critical_recovery_when_zoho_voided_but_local_needs_retry(): void
    {
        config(['zoho.payments.dry_run' => true]);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer, ['balance' => '18.0000']);
        $this->makeActiveAssignment($manager, $customer, $collector);

        $payment = $this->confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, '18.0000', $branch);

        $this->actingAsUser($collectorUser);
        $this->postJson('/api/v1/payments/'.$payment->uuid.'/reversal-request', [
            'reason' => 'Critical recovery path',
        ])->assertCreated();

        $conflict = CustodyConflict::withoutGlobalScopes()->where('payment_id', $payment->id)->firstOrFail();
        $conflict->zoho_void_status = CustodyAwareReversalService::ZOHO_VOIDED;
        $conflict->status = CustodyAwareReversalService::STATUS_CRITICAL_RECOVERY;
        $conflict->critical_alert = true;
        $conflict->save();

        $this->actingAsUser($manager);
        app(CustodyAwareReversalService::class)->approve($conflict->fresh(), $manager);

        $this->assertTrue($payment->fresh()->isReversed());
        $this->assertSame('0.0000', Money::normalize((string) BranchCashbox::withoutGlobalScopes()->where('branch_id', $branch->id)->value('balance')));
        $this->assertSame(CustodyAwareReversalService::STATUS_APPROVED, $conflict->fresh()->status);
    }

    protected function confirmAndHandOver($collectorUser, $manager, $customer, $collector, $invoice, string $amount, Branch $branch): Payment
    {
        config(['zoho.payments.dry_run' => true]);
        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), $amount
        ))->assertCreated()->json('data');
        $paymentData = $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk()->json('data');

        $handover = $this->postJson('/api/v1/cash-handovers/draft', [
            'branch_id' => $branch->id,
            'payment_ids' => [$paymentData['id']],
            'declared_amount' => $amount,
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/submit')->assertOk();

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/cash-handovers/'.$handover['id'].'/approve', [
            'counted_amount' => $amount,
            'approved_amount' => $amount,
        ])->assertOk();

        return Payment::withoutGlobalScopes()->findOrFail($paymentData['id']);
    }
}
