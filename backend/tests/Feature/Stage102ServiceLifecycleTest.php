<?php

namespace Tests\Feature;

use App\Models\Inventory\CustomerEquipment;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\User;
use App\Models\Services\Service;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceRelocation;
use App\Models\Tickets\Installation;
use App\Services\Installations\InstallationQueueService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage10AccessTechnologySeeder;
use Database\Seeders\Stage10ServiceTypeSeeder;
use Database\Seeders\Stage10SlaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage102ServiceLifecycleTest extends TestCase
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

    public function test_activation_checklist_is_evidence_based_not_client_flags(): void
    {
        [$manager, $service] = $this->makeDraftService('S102A');
        $this->actingAsUser($manager);

        // Ensure no Zoho link evidence from factory defaults.
        $service->zoho_customer_id = null;
        $service->installation_id = null;
        $service->save();
        $service->customer->zoho_contact_id = null;
        $service->customer->save();

        // Client claiming all checklist keys true must still fail without evidence.
        $this->postJson("/api/v1/services/{$service->id}/activate", [
            'idempotency_key' => 'ev-fail',
            'checklist' => [
                'zoho_linked' => true,
                'installation_completed' => true,
                'inventory_reconciled' => true,
                'equipment_assigned' => true,
            ],
        ])->assertStatus(422);

        $checklist = $this->getJson("/api/v1/services/{$service->id}/activation-checklist")
            ->assertOk()
            ->json('data');

        $this->assertFalse($checklist['checklist']['zoho_linked']);
        $this->assertNotEmpty($checklist['missing']);

        // Seed evidence
        $service->zoho_customer_id = 'ZOHO-123';
        $service->save();

        $installation = app(InstallationQueueService::class)->create([
            'branch_id' => $service->branch_id,
            'customer_id' => $service->customer_id,
            'prospect_name' => 'Evidence',
            'phone' => '0700000001',
            'address' => 'A',
        ], $manager);
        $installation->status = Installation::STATUS_COMPLETED;
        $installation->save();
        $service->installation_id = $installation->id;
        $service->save();

        $category = ProductCategory::query()->first()
            ?? ProductCategory::query()->create(['name_en' => 'CPE', 'code' => 'cpe-102', 'is_active' => true]);
        $product = Product::query()->create([
            'code' => 'CPE-102',
            'name_en' => 'ONU',
            'category_id' => $category->id,
            'tracking_mode' => Product::TRACK_QUANTITY,
            'is_active' => true,
        ]);
        CustomerEquipment::withoutGlobalScopes()->create([
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'status' => 'installed',
            'ownership_type' => 'company_owned',
        ]);

        $this->postJson("/api/v1/services/{$service->id}/activate", [
            'idempotency_key' => 'ev-ok',
        ])->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_ACTIVE)
            ->assertJsonPath('data.operational_status', Service::OPERATIONAL_READY)
            ->assertJsonPath('data.billing_status', Service::BILLING_NOT_BILLED);

        // Online only via NOC confirm
        $this->assertNotSame(Service::OPERATIONAL_ONLINE, $service->fresh()->operational_status);
        $this->postJson("/api/v1/services/{$service->id}/noc-confirm-online", [
            'reason' => 'Link verified by NOC',
        ])->assertOk()
            ->assertJsonPath('data.operational_status', Service::OPERATIONAL_ONLINE);
    }

    public function test_change_request_cannot_auto_apply_by_omitting_boolean(): void
    {
        [$manager, $service] = $this->makeActiveService('S102C');
        $this->actingAsUser($manager);

        $newPackageId = $this->postJson('/api/v1/service-packages', [
            'code' => 'NET-102',
            'name' => '102 Mbps',
            'branch_id' => $service->branch_id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 2200,
        ])->json('data.id');

        $cr = $this->postJson("/api/v1/services/{$service->id}/change-requests", [
            'requested_values' => ['package_id' => $newPackageId],
            'reason' => 'Upgrade',
        ])->assertCreated()->json('data');

        $this->assertSame(ServiceChangeRequest::STATUS_REQUESTED, $cr['status']);
        $this->assertSame((int) $service->package_id, (int) $service->fresh()->package_id);

        $this->postJson("/api/v1/service-change-requests/{$cr['id']}/advance", [
            'step' => 'technical',
        ])->assertOk();

        $this->postJson("/api/v1/service-change-requests/{$cr['id']}/advance", [
            'step' => 'finance',
        ])->assertOk()
            ->assertJsonPath('data.status', ServiceChangeRequest::STATUS_APPROVED);

        $this->postJson("/api/v1/service-change-requests/{$cr['id']}/advance", [
            'step' => 'apply',
        ])->assertOk()
            ->assertJsonPath('data.status', ServiceChangeRequest::STATUS_APPLIED);

        $this->assertSame($newPackageId, (int) $service->fresh()->package_id);
    }

    public function test_relocation_incomplete_by_default(): void
    {
        [$manager, $service] = $this->makeActiveService('S102R');
        $this->actingAsUser($manager);

        $newLocationId = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'name' => 'New site 102',
            'address' => 'Street 102',
        ])->json('data.id');

        $relocation = $this->postJson("/api/v1/services/{$service->id}/relocations", [
            'new_location_id' => $newLocationId,
        ])->assertCreated()->json('data');

        $this->assertSame(ServiceRelocation::STATUS_REQUESTED, $relocation['status']);
        $this->assertNotSame($newLocationId, (int) $service->fresh()->service_location_id);

        $this->postJson("/api/v1/service-relocations/{$relocation['id']}/start")->assertOk();
        $this->postJson("/api/v1/service-relocations/{$relocation['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.service_location_id', $newLocationId);
    }

    public function test_cancellation_requires_steps_before_complete(): void
    {
        [$manager, $service] = $this->makeActiveService('S102K');
        $this->actingAsUser($manager);

        $cancellation = $this->postJson("/api/v1/services/{$service->id}/cancel", [
            'reason' => 'Moved',
            'equipment_return_required' => true,
        ])->assertCreated()->json('data');

        $this->assertSame(ServiceCancellation::STATUS_REQUESTED, $cancellation['status']);
        $this->assertSame(Service::COMMERCIAL_PENDING_CANCELLATION, $service->fresh()->commercial_status);

        $this->postJson("/api/v1/service-cancellations/{$cancellation['id']}/complete")
            ->assertStatus(422);

        $this->postJson("/api/v1/service-cancellations/{$cancellation['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ServiceCancellation::STATUS_EQUIPMENT_RECOVERY);

        $this->postJson("/api/v1/service-cancellations/{$cancellation['id']}/complete")
            ->assertStatus(422);

        $this->postJson("/api/v1/service-cancellations/{$cancellation['id']}/recover-equipment", [
            'notes' => 'CPE recovered',
        ])->assertOk();

        $this->postJson("/api/v1/service-cancellations/{$cancellation['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.commercial_status', Service::COMMERCIAL_CANCELLED);
    }

    public function test_finance_hold_denied_without_manage_permission(): void
    {
        [$manager, $service] = $this->makeActiveService('S102F');

        $limited = User::factory()->create();
        $limited->givePermissionTo(['services.view', 'services.update']);
        $limited->branches()->attach($service->branch_id);
        $this->actingAsUser($limited);

        // services.update alone cannot place finance holds
        $this->postJson("/api/v1/services/{$service->id}/finance-holds", [
            'reason' => 'Overdue',
        ])->assertForbidden();

        $this->actingAsUser($manager);
        $this->postJson("/api/v1/services/{$service->id}/finance-holds", [
            'reason' => 'Overdue',
        ])->assertCreated();
    }

    public function test_queue_apis_return_real_records(): void
    {
        [$manager, $service] = $this->makeActiveService('S102Q');
        $this->actingAsUser($manager);

        $this->postJson("/api/v1/services/{$service->id}/change-requests", [
            'requested_values' => ['package_id' => $service->package_id],
        ])->assertCreated();

        $loc = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $service->customer_id,
            'branch_id' => $service->branch_id,
            'name' => 'Queue loc',
        ])->json('data.id');

        $this->postJson("/api/v1/services/{$service->id}/relocations", [
            'new_location_id' => $loc,
        ])->assertCreated();

        $this->postJson("/api/v1/services/{$service->id}/finance-holds", [
            'reason' => 'Queue hold',
        ])->assertCreated();

        $this->getJson('/api/v1/service-change-requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/service-relocations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/service-finance-holds')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
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
            'skip_checklist' => true,
        ])->assertOk();
        $this->postJson("/api/v1/services/{$service->id}/noc-confirm-online", [
            'reason' => 'test',
        ])->assertOk();

        return [$manager, $service->fresh()];
    }
}
