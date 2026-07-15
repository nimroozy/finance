<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoUnmappedCustomerIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_branch_manager_cannot_see_unmapped_customers(): void
    {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_BRANCH_MANAGER);
        $manager->branches()->attach($branch->id);

        Customer::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'zoho_contact_id' => 'MAPPED-1',
            'contact_name' => 'Mapped',
            'currency' => 'AFN',
            'status' => 'active',
            'is_unmapped' => false,
            'sync_status' => 'synced',
        ]);

        Customer::withoutGlobalScopes()->create([
            'branch_id' => null,
            'zoho_contact_id' => 'UNMAP-1',
            'contact_name' => 'Unmapped',
            'currency' => 'AFN',
            'status' => Customer::STATUS_UNMAPPED,
            'is_unmapped' => true,
            'sync_status' => 'synced',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/customers');
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('contact_name');
        $this->assertTrue($names->contains('Mapped'));
        $this->assertFalse($names->contains('Unmapped'));
    }

    public function test_central_finance_can_list_unmapped_endpoint(): void
    {
        $central = User::factory()->create();
        $central->assignRole(User::ROLE_CENTRAL_FINANCE);

        Customer::withoutGlobalScopes()->create([
            'branch_id' => null,
            'zoho_contact_id' => 'UNMAP-2',
            'contact_name' => 'Needs Mapping',
            'currency' => 'AFN',
            'status' => Customer::STATUS_UNMAPPED,
            'is_unmapped' => true,
            'sync_status' => 'synced',
        ]);

        Sanctum::actingAs($central);

        $this->getJson('/api/v1/customers/unmapped')
            ->assertOk()
            ->assertJsonFragment(['contact_name' => 'Needs Mapping']);
    }
}
