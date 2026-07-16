<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZohoLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoLocationMappingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_location_mapping_review_lists_locations(): void
    {
        $this->admin();
        ZohoLocation::query()->create([
            'organization_id' => 'org-1',
            'zoho_location_id' => 'loc-1',
            'name' => 'Kabul',
            'code' => 'KBL',
            'is_active' => true,
            'sync_status' => 'synced',
        ]);

        $this->getJson('/api/v1/zoho/location-mapping-review')
            ->assertOk()
            ->assertJsonPath('data.0.zoho_location_id', 'loc-1');
    }

    public function test_import_branch_decision_requires_confirmation(): void
    {
        $this->admin();
        ZohoLocation::query()->create([
            'organization_id' => 'org-1',
            'zoho_location_id' => 'loc-2',
            'name' => 'Helmand',
            'code' => 'HLM',
            'is_active' => true,
            'sync_status' => 'synced',
        ]);

        $this->postJson('/api/v1/zoho/locations/loc-2/mapping-decision', [
            'action' => 'import_branch',
        ])->assertStatus(422);

        $this->postJson('/api/v1/zoho/locations/loc-2/mapping-decision', [
            'action' => 'import_branch',
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('data.decision', 'import_branch');

        $this->assertDatabaseHas('branches', ['zoho_location_id' => 'loc-2', 'name_en' => 'Helmand']);
    }

    public function test_conflicts_endpoint_classifies_multi_location_customers(): void
    {
        $this->admin();
        $customer = Customer::factory()->create(['branch_id' => null, 'is_unmapped' => true]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'INV-A',
            'invoice_number' => 'A-1',
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '10.0000',
            'balance' => '10.0000',
            'zoho_location_id' => 'LOC-A',
            'sync_status' => 'synced',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'INV-B',
            'invoice_number' => 'B-1',
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '20.0000',
            'balance' => '20.0000',
            'zoho_location_id' => 'LOC-B',
            'sync_status' => 'synced',
        ]);

        $this->getJson('/api/v1/zoho/mapping-conflicts')
            ->assertOk()
            ->assertJsonPath('data.0.classification', 'multi_location_conflict');
    }

    public function test_customer_and_invoice_counts_use_distinct_ids(): void
    {
        Customer::factory()->count(3)->create();
        $customer = Customer::factory()->create();
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'INV-1',
            'invoice_number' => '1',
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '1.0000',
            'balance' => '1.0000',
            'sync_status' => 'synced',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'zoho_invoice_id' => 'INV-2',
            'invoice_number' => '2',
            'status' => 'sent',
            'currency' => 'AFN',
            'total' => '2.0000',
            'balance' => '2.0000',
            'sync_status' => 'synced',
        ]);

        $this->assertSame(4, Customer::query()->count());
        $this->assertSame(4, (int) Customer::query()->selectRaw('COUNT(DISTINCT id) as c')->value('c'));
        $this->assertSame(2, Invoice::withoutGlobalScopes()->count());
        $this->assertSame(2, (int) Invoice::withoutGlobalScopes()->selectRaw('COUNT(DISTINCT id) as c')->value('c'));
    }
}
