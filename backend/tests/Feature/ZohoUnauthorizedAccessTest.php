<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoUnauthorizedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/zoho/status')->assertStatus(401);
        $this->getJson('/api/v1/customers')->assertStatus(401);
        $this->getJson('/api/v1/invoices')->assertStatus(401);
        $this->getJson('/api/v1/debtors')->assertStatus(401);
    }

    public function test_collector_cannot_access_debtors_or_zoho_sync(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_COLLECTOR);
        Sanctum::actingAs($user);

        // Stage 3: customers/invoices.view allowed but scoped to active assignments (empty here).
        $this->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/debtors')->assertStatus(403);
        $this->getJson('/api/v1/debtors/export')->assertStatus(403);
        $this->postJson('/api/v1/zoho/sync/customers')->assertStatus(403);
    }
}
