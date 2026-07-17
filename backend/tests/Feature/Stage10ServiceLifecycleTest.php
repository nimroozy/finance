<?php

namespace Tests\Feature;

use App\Events\BusinessNotificationRequested;
use App\Models\Payment;
use App\Models\Services\Service;
use App\Models\Services\ServicePackage;
use App\Models\Services\ServicePackageVersion;
use App\Models\Tickets\Installation;
use App\Services\Installations\InstallationQueueService;
use App\Services\ServiceLifecycle\ServicePackageService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage10AccessTechnologySeeder;
use Database\Seeders\Stage10ServiceTypeSeeder;
use Database\Seeders\Stage10SlaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage10ServiceLifecycleTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage10ServiceTypeSeeder::class);
        $this->seed(Stage10AccessTechnologySeeder::class);
        $this->seed(Stage10SlaTemplateSeeder::class);
        Http::fake();
    }

    public function test_radius_feature_flag_remains_disabled(): void
    {
        $this->assertFalse(config('service_lifecycle.radius.enabled'));
        $this->assertFalse(config('platform.modules.radius.enabled'));
    }

    public function test_service_types_and_technologies_seeded(): void
    {
        $branch = $this->makeBranch(['code' => 'S10A']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $this->getJson('/api/v1/service-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'internet']);

        $this->getJson('/api/v1/service-access-technologies')
            ->assertOk()
            ->assertJsonFragment(['code' => 'ftth']);
    }

    public function test_package_create_versions_immutable_prices(): void
    {
        $branch = $this->makeBranch(['code' => 'S10P']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $id = $this->postJson('/api/v1/service-packages', [
            'code' => 'NET-50',
            'name' => '50 Mbps',
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 1000,
            'installation_fee' => 500,
            'download_mbps' => 50,
            'upload_mbps' => 20,
        ])->assertCreated()->json('data.id');

        $version = ServicePackageVersion::query()->where('package_id', $id)->firstOrFail();
        $this->expectException(LogicException::class);
        $version->update(['monthly_price' => 9999]);
    }

    public function test_package_versioning_creates_new_version_without_mutating_old(): void
    {
        $branch = $this->makeBranch(['code' => 'S10V']);
        $manager = $this->makeManager($branch);
        $packages = app(ServicePackageService::class);
        $package = $packages->create([
            'code' => 'NET-100',
            'name' => '100 Mbps',
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'wireless',
            'monthly_price' => 1500,
        ], $manager);

        $v1 = $package->latestVersion();
        $v2 = $packages->createVersion($package, [
            'monthly_price' => 1800,
            'installation_fee' => 600,
            'download_mbps' => 100,
            'upload_mbps' => 40,
        ], $manager);

        $this->assertSame(1, (int) $v1->version);
        $this->assertSame(2, (int) $v2->version);
        $this->assertEquals('1500.00', (string) $v1->fresh()->monthly_price);
        $this->assertEquals('1800.00', (string) $v2->monthly_price);
    }

    public function test_service_create_and_branch_isolation(): void
    {
        $branchA = $this->makeBranch(['code' => 'S10B']);
        $branchB = $this->makeBranch(['code' => 'S10C']);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        $customer = $this->makeCustomer($branchA);

        $this->actingAsUser($managerA);
        $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
        ])->assertCreated()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_DRAFT)
            ->assertJsonPath('data.branch_id', $branchA->id);

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsUser($managerA);
        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_service_number_uses_lock_for_update_sequence(): void
    {
        $branch = $this->makeBranch(['code' => 'S10N']);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);
        $this->actingAsUser($manager);

        $a = $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
        ])->json('data.service_number');

        $b = $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
        ])->json('data.service_number');

        $this->assertNotSame($a, $b);
        $this->assertStringContainsString('S10N-SVC-', $a);
    }

    public function test_activation_idempotency_and_checklist(): void
    {
        Event::fake([BusinessNotificationRequested::class]);
        [$manager, $service] = $this->makeDraftService('S10X');
        $this->actingAsUser($manager);

        $checklist = [
            'customer_confirmed',
            'equipment_installed',
            'link_tested',
            'documentation_complete',
        ];

        $this->postJson("/api/v1/services/{$service->id}/activate", [
            'idempotency_key' => 'act-1',
            'checklist' => $checklist,
        ])->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_ACTIVE)
            ->assertJsonPath('data.operational_status', Service::OPERATIONAL_ONLINE);

        $this->postJson("/api/v1/services/{$service->id}/activate", [
            'idempotency_key' => 'act-1',
            'checklist' => $checklist,
        ])->assertOk();

        $this->assertSame(1, $service->activations()->count());
        Event::assertDispatched(BusinessNotificationRequested::class);
    }

    public function test_suspend_and_reactivate(): void
    {
        Event::fake([BusinessNotificationRequested::class]);
        [$manager, $service] = $this->makeActiveService('S10S');
        $this->actingAsUser($manager);

        $this->postJson("/api/v1/services/{$service->id}/suspend", [
            'reason' => 'Customer request',
            'type' => 'voluntary',
        ])->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_SUSPENDED);

        $this->postJson("/api/v1/services/{$service->id}/reactivate", [
            'reason' => 'Payment received',
        ])->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_ACTIVE)
            ->assertJsonPath('data.operational_status', Service::OPERATIONAL_ONLINE);
    }

    public function test_cancellation_with_equipment_return_flag(): void
    {
        [$manager, $service] = $this->makeActiveService('S10K');
        $this->actingAsUser($manager);

        $this->postJson("/api/v1/services/{$service->id}/cancel", [
            'reason' => 'Moved city',
            'equipment_return_required' => true,
            'complete' => true,
        ])->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_CANCELLED)
            ->assertJsonPath('data.operational_status', Service::OPERATIONAL_DECOMMISSIONED);

        $this->assertDatabaseHas('service_cancellations', [
            'service_id' => $service->id,
            'equipment_return_required' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_invalid_commercial_transition_rejected(): void
    {
        [$manager, $service] = $this->makeDraftService('S10T');
        $this->actingAsUser($manager);

        $this->postJson("/api/v1/services/{$service->id}/transition", [
            'commercial' => Service::COMMERCIAL_ACTIVE,
        ])->assertStatus(422);
    }

    public function test_package_change_request_applies_new_version(): void
    {
        [$manager, $service] = $this->makeActiveService('S10G');
        $this->actingAsUser($manager);

        $newPackageId = $this->postJson('/api/v1/service-packages', [
            'code' => 'NET-200',
            'name' => '200 Mbps',
            'branch_id' => $service->branch_id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 2500,
            'download_mbps' => 200,
            'upload_mbps' => 50,
        ])->json('data.id');

        $this->postJson("/api/v1/services/{$service->id}/change-requests", [
            'requested_values' => ['package_id' => $newPackageId],
            'apply' => true,
            'reason' => 'Upgrade',
        ])->assertCreated();

        $this->assertSame($newPackageId, (int) $service->fresh()->package_id);
        $this->assertEquals('2500.00', (string) $service->fresh()->mrr);
    }

    public function test_relocation_updates_location(): void
    {
        [$manager, $service] = $this->makeActiveService('S10R');
        $this->actingAsUser($manager);

        $newLocationId = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'name' => 'New site',
            'address' => 'Street 2',
        ])->json('data.id');

        $this->postJson("/api/v1/services/{$service->id}/relocations", [
            'new_location_id' => $newLocationId,
            'complete' => true,
        ])->assertOk()
            ->assertJsonPath('data.service_location_id', $newLocationId);
    }

    public function test_finance_hold_and_release(): void
    {
        [$manager, $service] = $this->makeActiveService('S10F');
        $this->actingAsUser($manager);

        $holdId = $this->postJson("/api/v1/services/{$service->id}/finance-holds", [
            'reason' => 'Overdue invoice',
            'hold_type' => 'overdue',
            'outstanding_snapshot' => ['balance' => 100],
        ])->assertCreated()->json('data.id');

        $this->assertSame(Service::BILLING_ON_HOLD, $service->fresh()->billing_status);

        $this->postJson("/api/v1/service-finance-holds/{$holdId}/release")
            ->assertOk();

        $this->assertSame(Service::BILLING_CURRENT, $service->fresh()->billing_status);
    }

    public function test_billing_view_is_read_only_and_payment_counts_stable(): void
    {
        [$manager, $service] = $this->makeActiveService('S10Z');
        $this->actingAsUser($manager);

        $paymentCountBefore = Payment::query()->count();

        $response = $this->getJson("/api/v1/services/{$service->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.read_only', true)
            ->assertJsonPath('data.source_of_truth', 'zoho');

        $this->assertSame($paymentCountBefore, Payment::query()->count());
        $this->assertArrayHasKey('sync_timestamps', $response->json('data'));
    }

    public function test_convert_installation_to_service(): void
    {
        $branch = $this->makeBranch(['code' => 'S10I']);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);
        $this->actingAsUser($manager);

        $installation = app(InstallationQueueService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'prospect_name' => 'Prospect',
            'phone' => '0700111222',
            'address' => 'Main St',
            'requested_package' => 'NET-50',
        ], $manager);

        $packageId = $this->postJson('/api/v1/service-packages', [
            'code' => 'NET-50',
            'name' => '50 Mbps',
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 1000,
        ])->json('data.id');

        $this->postJson("/api/v1/installations/{$installation->id}/convert-to-service", [
            'package_id' => $packageId,
        ])->assertCreated()
            ->assertJsonPath('data.installation_id', $installation->id)
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_DRAFT);

        // Idempotent convert
        $this->postJson("/api/v1/installations/{$installation->id}/convert-to-service", [
            'package_id' => $packageId,
        ])->assertCreated();

        $this->assertSame(1, Service::withoutGlobalScopes()->where('installation_id', $installation->id)->count());
    }

    public function test_renewal_extends_expiration(): void
    {
        [$manager, $service] = $this->makeActiveService('S10W');
        $service->expiration_date = now()->addDays(5)->toDateString();
        $service->save();
        $this->actingAsUser($manager);

        $this->postJson("/api/v1/services/{$service->id}/renewals", [
            'term_months' => 12,
        ])->assertCreated();

        $this->assertTrue($service->fresh()->expiration_date->greaterThan(now()->addMonths(6)));
    }

    public function test_contract_create_and_dashboard(): void
    {
        [$manager, $service] = $this->makeActiveService('S10D');
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/service-contracts', [
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'service_id' => $service->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/v1/services/dashboard')
            ->assertOk()
            ->assertJsonPath('data.radius_enabled', false)
            ->assertJsonPath('data.total', 1);
    }

    public function test_migration_dry_run_and_whatsapp_mocked(): void
    {
        Http::fake();
        Event::fake([BusinessNotificationRequested::class]);

        $branch = $this->makeBranch(['code' => 'S10M']);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);
        $this->actingAsUser($manager);

        $installation = app(InstallationQueueService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'prospect_name' => 'Migrate Me',
        ], $manager);
        $installation->status = Installation::STATUS_COMPLETED;
        $installation->save();

        $this->postJson('/api/v1/services/migration/dry-run', [
            'branch_id' => $branch->id,
        ])->assertOk()
            ->assertJsonPath('data.mode', 'dry_run');

        $this->postJson('/api/v1/services/migration/apply', [
            'branch_id' => $branch->id,
        ])->assertOk()
            ->assertJsonPath('data.created', 1);

        // No outbound HTTP to Radius / Meta from lifecycle path in this test.
        Http::assertNothingSent();
    }

    public function test_stage9_inventory_still_green_smoke(): void
    {
        // Ensure Stage 10 seeders/migrations do not break Stage 9 permission surface.
        $branch = $this->makeBranch(['code' => 'S9OK']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $this->getJson('/api/v1/inventory/categories')->assertOk();
        $this->assertTrue($manager->can('inventory.products.view'));
        $this->assertTrue($manager->can('services.view'));
        $this->assertFalse(config('platform.modules.radius.enabled'));
    }

    /**
     * @return array{0: \App\Models\User, 1: Service}
     */
    private function makeDraftService(string $branchCode): array
    {
        $branch = $this->makeBranch(['code' => $branchCode]);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);
        $this->actingAsUser($manager);

        $packageId = $this->postJson('/api/v1/service-packages', [
            'code' => 'PKG-'.$branchCode,
            'name' => 'Package '.$branchCode,
            'branch_id' => $branch->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 1200,
        ])->json('data.id');

        $locationId = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'name' => 'Primary',
        ])->json('data.id');

        $id = $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'package_id' => $packageId,
            'service_location_id' => $locationId,
        ])->json('data.id');

        return [$manager, Service::query()->findOrFail($id)];
    }

    /**
     * @return array{0: \App\Models\User, 1: Service}
     */
    private function makeActiveService(string $branchCode): array
    {
        [$manager, $service] = $this->makeDraftService($branchCode);
        $this->actingAsUser($manager);
        $this->postJson("/api/v1/services/{$service->id}/activate", [
            'idempotency_key' => 'act-'.$branchCode,
            'checklist' => [
                'customer_confirmed',
                'equipment_installed',
                'link_tested',
                'documentation_complete',
            ],
        ])->assertOk();

        return [$manager, $service->fresh()];
    }
}
