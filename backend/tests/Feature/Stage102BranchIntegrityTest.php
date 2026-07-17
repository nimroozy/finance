<?php

namespace Tests\Feature;

use App\Models\Services\Service;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage10AccessTechnologySeeder;
use Database\Seeders\Stage10ServiceTypeSeeder;
use Database\Seeders\Stage10SlaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage102BranchIntegrityTest extends TestCase
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

    public function test_create_rejects_customer_from_other_branch(): void
    {
        $branchA = $this->makeBranch(['code' => 'BR-A']);
        $branchB = $this->makeBranch(['code' => 'BR-B']);
        $manager = $this->makeManager($branchA);
        $manager->branches()->attach($branchB->id);
        $foreignCustomer = $this->makeCustomer($branchB);
        $this->actingAsUser($manager);

        $this->postJson('/api/v1/services', [
            'customer_id' => $foreignCustomer->id,
            'branch_id' => $branchA->id,
        ])->assertStatus(422);
    }

    public function test_create_rejects_location_from_other_branch(): void
    {
        $branchA = $this->makeBranch(['code' => 'LOC-A']);
        $branchB = $this->makeBranch(['code' => 'LOC-B']);
        $manager = $this->makeManager($branchA);
        $manager->branches()->attach($branchB->id);
        $customer = $this->makeCustomer($branchA);
        $this->actingAsUser($manager);

        $foreignLocation = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $this->makeCustomer($branchB)->id,
            'branch_id' => $branchB->id,
            'name' => 'Foreign',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'service_location_id' => $foreignLocation,
        ])->assertStatus(422);
    }

    public function test_create_rejects_package_from_other_branch(): void
    {
        $branchA = $this->makeBranch(['code' => 'PKG-A']);
        $branchB = $this->makeBranch(['code' => 'PKG-B']);
        $manager = $this->makeManager($branchA);
        $manager->branches()->attach($branchB->id);
        $customer = $this->makeCustomer($branchA);
        $this->actingAsUser($manager);

        $foreignPackage = $this->postJson('/api/v1/service-packages', [
            'code' => 'PKG-FOREIGN',
            'name' => 'Foreign',
            'branch_id' => $branchB->id,
            'service_type_code' => 'internet',
            'access_technology_code' => 'ftth',
            'monthly_price' => 500,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'package_id' => $foreignPackage,
        ])->assertStatus(422);
    }

    public function test_update_rejects_location_from_other_branch(): void
    {
        $branchA = $this->makeBranch(['code' => 'UPD-A']);
        $branchB = $this->makeBranch(['code' => 'UPD-B']);
        $manager = $this->makeManager($branchA);
        $manager->branches()->attach($branchB->id);
        $customer = $this->makeCustomer($branchA);
        $this->actingAsUser($manager);

        $serviceId = $this->postJson('/api/v1/services', [
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
        ])->assertCreated()->json('data.id');

        $foreignLocation = $this->postJson('/api/v1/service-locations', [
            'customer_id' => $this->makeCustomer($branchB)->id,
            'branch_id' => $branchB->id,
            'name' => 'Other',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/services/{$serviceId}", [
            'service_location_id' => $foreignLocation,
        ])->assertStatus(422);

        $this->assertNull(Service::query()->findOrFail($serviceId)->service_location_id);
    }
}
