<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchCustomerPrefix;
use App\Models\Customer;
use App\Models\CustomerBranchMappingConflict;
use App\Models\Invoice;
use App\Services\Mapping\CustomerBranchResolutionService;
use App\Services\Mapping\CustomerNumberPrefixMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class CustomerPrefixMappingTest extends TestCase
{
    use CreatesCollectionFixtures, CreatesPaymentFixtures, RefreshDatabase;

    protected function seedPrefixes(): array
    {
        $kabul = $this->makeBranch(['code' => 'KABUL', 'name_en' => 'Kabul', 'name_fa' => 'کابل', 'receipt_prefix' => 'KBL']);
        $nimruz = $this->makeBranch(['code' => '01', 'name_en' => 'Nimruz', 'name_fa' => 'نیمروز', 'receipt_prefix' => 'NMZ']);
        $ghazni = $this->makeBranch(['code' => 'GHAZNI', 'name_en' => 'Ghazni', 'receipt_prefix' => 'GHZ']);
        $buldak = $this->makeBranch(['code' => 'BULDAK', 'name_en' => 'Buldak', 'receipt_prefix' => 'BLD']);
        $kandahar = $this->makeBranch(['code' => 'KANDAHAR', 'name_en' => 'Kandahar', 'receipt_prefix' => 'KDR']);
        $helmand = $this->makeBranch(['code' => 'HELMAND', 'name_en' => 'Helmand', 'receipt_prefix' => 'HLD']);

        foreach ([
            ['KBL', $kabul], ['NMZ', $nimruz], ['GHZ', $ghazni],
            ['BLD', $buldak], ['KDR', $kandahar], ['HLD', $helmand],
        ] as [$prefix, $branch]) {
            BranchCustomerPrefix::query()->create([
                'branch_id' => $branch->id,
                'prefix' => $prefix,
                'normalized_prefix' => $prefix,
                'active' => true,
                'priority' => 100,
            ]);
        }

        return compact('kabul', 'nimruz', 'ghazni', 'buldak', 'kandahar', 'helmand');
    }

    public function test_prefix_normalization_and_separators(): void
    {
        $branches = $this->seedPrefixes();
        $matcher = app(CustomerNumberPrefixMatcher::class);

        foreach (['KBL-001', 'KBL001', 'kbl_001', 'KBL 001', 'KBL/001'] as $num) {
            $hit = $matcher->detect($num);
            $this->assertNotNull($hit, $num);
            $this->assertSame('KBL', $hit['prefix']);
            $this->assertSame($branches['kabul']->id, $hit['branch_id']);
        }

        foreach (['NMZ 1200', 'GHZ/500', 'BLD-90', 'KDR0001', 'HLD_554'] as $num) {
            $this->assertNotNull($matcher->detect($num), $num);
        }
    }

    public function test_prefix_must_be_at_beginning_only(): void
    {
        $this->seedPrefixes();
        $matcher = app(CustomerNumberPrefixMatcher::class);
        $this->assertNull($matcher->detect('CUSTOMER-KBL-001'));
        $this->assertNull($matcher->detect('ABC-001'));
        $this->assertNull($matcher->detect(''));
        $this->assertNull($matcher->detect(null));
        $this->assertNull($matcher->detect('001254'));
    }

    public function test_duplicate_active_prefix_prevented_via_api(): void
    {
        $branches = $this->seedPrefixes();
        $admin = $this->makeSuperAdmin();
        $this->actingAsUser($admin);

        $this->postJson('/api/v1/customer-prefix-mappings', [
            'branch_id' => $branches['kabul']->id,
            'prefix' => 'kbl',
        ])->assertStatus(422);
    }

    public function test_resolution_prefix_and_admin_override_priority(): void
    {
        $branches = $this->seedPrefixes();
        $customer = $this->makeCustomer($branches['nimruz'], ['customer_number' => 'KBL-100']);
        $resolver = app(CustomerBranchResolutionService::class);
        $resolution = $resolver->resolve($customer);
        $this->assertSame(CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX, $resolution['resolution_source']);
        $this->assertSame($branches['kabul']->id, $resolution['resolved_branch_id']);

        $admin = $this->makeSuperAdmin();
        $resolver->setAdministratorOverride($customer, $branches['nimruz'], $admin, 'manual');
        $again = $resolver->resolve($customer->fresh());
        $this->assertSame(CustomerBranchResolutionService::SOURCE_ADMIN_OVERRIDE, $again['resolution_source']);
        $this->assertSame($branches['nimruz']->id, $again['resolved_branch_id']);
    }

    public function test_prefix_location_mismatch_creates_conflict(): void
    {
        $branches = $this->seedPrefixes();
        $customer = $this->makeCustomer($branches['kabul'], [
            'customer_number' => 'NMZ-1045',
            'branch_mapping_source' => null,
            'is_unmapped' => true,
            'branch_id' => null,
        ]);
        Invoice::withoutGlobalScopes()->create([
            'branch_id' => $branches['kabul']->id,
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'ZI-PX-1',
            'invoice_number' => 'KBL-INV-X',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '10',
            'amount_paid' => '0',
            'credits_applied' => '0',
            'balance' => '10',
            'zoho_location_id' => $branches['kabul']->zoho_location_id ?: 'LOC-KBL',
            'sync_status' => 'synced',
        ]);
        $branches['kabul']->update(['zoho_location_id' => $branches['kabul']->zoho_location_id ?: 'LOC-KBL']);

        $resolution = app(CustomerBranchResolutionService::class)->resolve($customer->fresh());
        $this->assertSame(CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH, $resolution['conflict_type']);
        app(CustomerBranchResolutionService::class)->apply($customer, $resolution, null, dryRun: false, runId: 'test');
        $this->assertDatabaseHas('customer_branch_mapping_conflicts', [
            'customer_id' => $customer->id,
            'conflict_type' => CustomerBranchMappingConflict::TYPE_PREFIX_LOCATION_MISMATCH,
            'status' => 'open',
        ]);
        $this->assertTrue($customer->fresh()->is_unmapped);
    }

    public function test_protected_history_blocks_auto_remap(): void
    {
        $branches = $this->seedPrefixes();
        $manager = $this->makeManager($branches['kabul']);
        [$collectorUser, $collector] = $this->makeCollectorUser($branches['kabul']);
        $customer = $this->makeCustomer($branches['kabul'], ['customer_number' => 'NMZ-200']);
        // Create active assignment = protected history
        $this->actingAsUser($manager);
        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertCreated();

        $resolution = app(CustomerBranchResolutionService::class)->resolve($customer->fresh());
        $this->assertTrue($resolution['protected_history']);
        $before = $customer->branch_id;
        app(CustomerBranchResolutionService::class)->apply($customer->fresh(), $resolution, null, dryRun: false, runId: 'prot');
        $this->assertSame($before, $customer->fresh()->branch_id);
    }

    public function test_dry_run_command_does_not_change_branches(): void
    {
        $branches = $this->seedPrefixes();
        $customer = $this->makeCustomer($branches['nimruz'], [
            'customer_number' => 'KBL-777',
            'is_unmapped' => true,
            'branch_id' => null,
            'status' => Customer::STATUS_UNMAPPED,
        ]);
        Artisan::call('customers:map-branches-by-prefix', ['--customer' => $customer->id]);
        $this->assertNull($customer->fresh()->branch_id);

        Artisan::call('customers:map-branches-by-prefix', ['--customer' => $customer->id, '--apply' => true]);
        $this->assertSame($branches['kabul']->id, $customer->fresh()->branch_id);
        $this->assertSame(CustomerBranchResolutionService::SOURCE_CUSTOMER_PREFIX, $customer->fresh()->branch_mapping_source);
    }

    public function test_api_test_number_and_index(): void
    {
        $this->seedPrefixes();
        $admin = $this->makeSuperAdmin();
        $this->actingAsUser($admin);
        $this->getJson('/api/v1/customer-prefix-mappings')->assertOk();
        $this->postJson('/api/v1/customer-prefix-mappings/test', [
            'customer_number' => 'nmz-55',
        ])->assertOk()->assertJsonPath('data.match.prefix', 'NMZ');
    }

    public function test_payment_blocked_on_prefix_location_mismatch(): void
    {
        $branches = $this->seedPrefixes();
        $branches['kabul']->update(['zoho_location_id' => 'LOC-KBL']);
        $branches['nimruz']->update(['zoho_location_id' => 'LOC-NMZ']);

        $admin = $this->makeSuperAdmin();
        $admin->branches()->attach([$branches['kabul']->id, $branches['nimruz']->id]);
        \App\Models\ZohoLocation::query()->create([
            'organization_id' => 'o', 'zoho_location_id' => 'LOC-KBL', 'name' => 'Kabul', 'is_active' => true, 'sync_status' => 'synced',
        ]);
        \App\Models\ZohoAccount::query()->create([
            'organization_id' => 'o', 'zoho_account_id' => 'ACC-KBL', 'name' => 'Kabul Cash', 'account_type' => 'cash', 'is_active' => true,
        ]);
        \App\Models\ZohoPaymentMode::query()->create([
            'organization_id' => 'o', 'zoho_payment_mode_id' => 'PM-1', 'name' => 'Cash', 'is_active' => true,
        ]);
        app(\App\Services\Ownership\BranchPaymentMappingService::class)
            ->upsert($branches['kabul'], $admin, 'ACC-KBL', 'PM-1', 'AFN', 'KBL', 'LOC-KBL');

        [$collectorUser, $collector] = $this->makeCollectorUser($branches['kabul']);
        $customer = $this->makeCustomer($branches['kabul'], [
            'customer_number' => 'NMZ-1045',
            'zoho_contact_id' => 'ZC-PX',
            'branch_mapping_source' => 'administrator_override',
            'administrator_branch_override' => true,
        ]);
        $invoice = Invoice::withoutGlobalScopes()->create([
            'branch_id' => $branches['kabul']->id,
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'ZI-PX-PAY',
            'invoice_number' => 'KBL-INV-PAY',
            'invoice_date' => now()->toDateString(),
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '10',
            'amount_paid' => '0',
            'credits_applied' => '0',
            'balance' => '10',
            'zoho_location_id' => 'LOC-KBL',
            'sync_status' => 'synced',
        ]);
        $this->makeActiveAssignment($admin, $customer, $collector);

        config(['zoho.payments.dry_run' => true]);
        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload(
            $customer, $collector, $invoice, $this->cashMethod(), '10.0000'
        ))->assertCreated()->json('data');
        $this->postJson('/api/v1/payments/'.$draft['uuid'].'/confirm')->assertOk();

        $payment = \App\Models\Payment::withoutGlobalScopes()->where('uuid', $draft['uuid'])->firstOrFail();
        config(['zoho.payments.dry_run' => false]);
        \Illuminate\Support\Facades\Http::fake();
        try {
            app(\App\Services\Zoho\ZohoPaymentSyncService::class)->sync($payment, forceLive: true);
            $this->fail('Expected LocalizedInvalidArgumentException');
        } catch (\App\Support\LocalizedInvalidArgumentException $e) {
            $this->assertStringContainsString('NMZ-1045', $e->getMessage());
            $this->assertStringContainsString('Nimruz', $e->getMessage());
            $this->assertStringContainsString('Kabul', $e->getMessage());
            $this->assertNotEmpty($e->messageFa);
        }
    }

    public function test_prefix_mapping_places_customer_in_unassigned_queue_without_auto_collector(): void
    {
        $branches = $this->seedPrefixes();
        $customer = $this->makeCustomer($branches['nimruz'], [
            'customer_number' => 'KBL-888',
            'is_unmapped' => true,
            'branch_id' => null,
            'status' => Customer::STATUS_UNMAPPED,
        ]);
        $resolution = app(CustomerBranchResolutionService::class)->resolve($customer);
        app(CustomerBranchResolutionService::class)->apply($customer, $resolution, null, dryRun: false, runId: 'own');
        $customer->refresh();
        $this->assertSame($branches['kabul']->id, $customer->branch_id);
        $queue = \App\Models\CustomerWorkQueue::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($queue);
        $this->assertNull($queue->effective_collector_id);
        $this->assertContains($queue->ownership_source, ['unassigned', 'conflict']);
    }

    protected function makeSuperAdmin()
    {
        $this->seedRoles();
        $user = \App\Models\User::factory()->create(['status' => 'active']);
        $user->assignRole(\App\Models\User::ROLE_SUPER_ADMIN);

        return $user;
    }
}
