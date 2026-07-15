<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->assertTrue(Role::where('name', User::ROLE_SUPER_ADMIN)->exists());
        $this->assertTrue(Permission::where('name', 'users.manage')->exists());

        $super = Role::findByName(User::ROLE_SUPER_ADMIN);
        $this->assertTrue($super->hasPermissionTo('settings.manage'));

        $collector = Role::findByName(User::ROLE_COLLECTOR);
        $this->assertFalse($collector->hasPermissionTo('users.manage'));
        $this->assertTrue($collector->hasPermissionTo('dashboard.view'));
    }

    public function test_collector_cannot_manage_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_COLLECTOR);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->postJson('/api/v1/users', [
            'name' => 'New',
            'email' => 'new@example.com',
            'password' => 'Password1!abc',
        ])->assertStatus(403);
    }

    public function test_super_admin_can_list_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'name', 'permissions']]]);
    }

    public function test_auditor_can_view_audit_but_not_manage_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_AUDITOR);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/audit-logs')->assertOk();
        $this->getJson('/api/v1/settings')->assertStatus(403);
    }
}
