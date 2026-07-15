<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoConnectionPermissionTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->createConnectedZoho();
    }

    public function test_super_admin_can_configure_and_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/zoho/status')->assertOk();
        $this->getJson('/api/v1/zoho/data-centers')->assertOk();
        $this->getJson('/api/v1/zoho/oauth/redirect')->assertOk();
    }

    public function test_central_finance_can_view_and_sync_but_not_configure(): void
    {
        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response([
                'contacts' => [],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $user = User::factory()->create();
        $user->assignRole(User::ROLE_CENTRAL_FINANCE);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/zoho/status')->assertOk();
        $this->postJson('/api/v1/zoho/sync/customers')->assertStatus(202);
        $this->getJson('/api/v1/zoho/data-centers')->assertStatus(403);
        $this->getJson('/api/v1/zoho/oauth/redirect')->assertStatus(403);
        $this->postJson('/api/v1/zoho/disconnect')->assertStatus(403);
    }

    public function test_branch_manager_cannot_access_zoho_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_BRANCH_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/zoho/status')->assertStatus(403);
    }

    public function test_auditor_can_view_zoho_but_not_configure(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_AUDITOR);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/zoho/status')->assertOk();
        $this->getJson('/api/v1/zoho/data-centers')->assertStatus(403);
        $this->postJson('/api/v1/zoho/sync/customers')->assertStatus(403);
    }

    public function test_collector_has_no_zoho_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_COLLECTOR);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/zoho/status')->assertStatus(403);
        // Stage 3 grants customers.view; isolation scopes return empty without assignments.
        $this->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/debtors')->assertStatus(403);
    }
}
