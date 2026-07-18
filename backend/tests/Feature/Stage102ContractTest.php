<?php

namespace Tests\Feature;

use App\Models\Services\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage10AccessTechnologySeeder;
use Database\Seeders\Stage10ServiceTypeSeeder;
use Database\Seeders\Stage10SlaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage102ContractTest extends TestCase
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

    public function test_service_show_includes_production_recovery_fields(): void
    {
        [$manager, $service] = $this->makeDraftService('S102C');
        $this->actingAsUser($manager);

        $service->forceFill([
            'tower_id' => 11,
            'site_id' => 22,
            'network_node' => 'NN-1',
            'vlan' => '100',
            'static_ip' => '10.0.0.5',
            'public_ip' => '1.2.3.4',
            'private_ip' => '192.168.1.10',
            'circuit_id' => 'CIR-9',
            'customer_reference' => 'CUST-REF',
            'technical_owner_id' => $manager->id,
            'sales_owner_id' => $manager->id,
            'support_owner_id' => $manager->id,
            'zoho_customer_id' => 'ZOHO-SVC',
        ])->save();

        $data = $this->getJson("/api/v1/services/{$service->id}")
            ->assertOk()
            ->json('data');

        foreach ([
            'tower_id', 'site_id', 'network_node', 'vlan', 'static_ip', 'public_ip', 'private_ip',
            'circuit_id', 'customer_reference', 'technical_owner_id', 'sales_owner_id', 'support_owner_id',
            'zoho_customer_id', 'start_date', 'activation_date', 'suspension_date', 'cancellation_date',
            'expiration_date', 'package_version_id', 'sla_template_id', 'installation_id',
            'equipment_summary', 'package_version', 'sla', 'installation',
        ] as $field) {
            $this->assertArrayHasKey($field, $data, "Missing field: {$field}");
        }

        $this->assertSame('CIR-9', $data['circuit_id']);
        $this->assertSame('ZOHO-SVC', $data['zoho_customer_id']);
        $this->assertIsArray($data['equipment_summary']);
        $this->assertArrayHasKey('total', $data['equipment_summary']);
    }

    public function test_service_index_includes_core_network_and_owner_fields(): void
    {
        [$manager, $service] = $this->makeDraftService('S102I');
        $this->actingAsUser($manager);
        $service->forceFill(['circuit_id' => 'IDX-1', 'tower_id' => 3])->save();

        $row = collect($this->getJson('/api/v1/services')->assertOk()->json('data'))
            ->firstWhere('id', $service->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('circuit_id', $row);
        $this->assertArrayHasKey('tower_id', $row);
        $this->assertArrayHasKey('equipment_summary', $row);
        $this->assertArrayHasKey('technical_owner_id', $row);
        $this->assertArrayHasKey('zoho_customer_id', $row);
    }

    public function test_stage102_service_permission_names_exist(): void
    {
        $expected = [
            'services.view',
            'services.create',
            'services.update',
            'services.activate',
            'services.activate.override',
            'services.suspend',
            'services.cancel',
            'services.change',
            'services.relocate',
            'services.renew',
            'services.packages.view',
            'services.locations.view',
            'services.finance_holds.manage',
            'services.noc.view',
            'services.dashboard.view',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                Permission::query()->where('name', $name)->where('guard_name', 'web')->exists(),
                "Missing permission: {$name}",
            );
        }
    }

    public function test_apps_counts_endpoint_returns_keys(): void
    {
        $branch = $this->makeBranch(['code' => 'S102CNT']);
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $data = $this->getJson('/api/v1/apps/counts')
            ->assertOk()
            ->json('data');

        foreach (['tasks', 'support', 'installations', 'services', 'noc'] as $key) {
            $this->assertArrayHasKey($key, $data);
            $this->assertIsInt($data[$key]);
        }
    }

    /**
     * @return array{0: User, 1: Service}
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
        ])->assertCreated()->json('data.id');

        return [$manager, Service::query()->findOrFail($id)];
    }
}
