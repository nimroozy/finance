<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'username' => 'adminuser',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@example.com',
            'password' => 'Password1!abc',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'username' => 'branch_mgr',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_BRANCH_MANAGER);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'branch_mgr',
            'password' => 'Password1!abc',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_failed_login_increments_attempts_and_locks_after_five(): void
    {
        $user = User::factory()->create([
            'email' => 'lock@example.com',
            'password' => 'Password1!abc',
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'login' => 'lock@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'login' => 'lock@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(423);

        $user->refresh();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->disabled()->create([
            'email' => 'disabled@example.com',
            'password' => 'Password1!abc',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'disabled@example.com',
            'password' => 'Password1!abc',
        ])->assertStatus(403);
    }
}
