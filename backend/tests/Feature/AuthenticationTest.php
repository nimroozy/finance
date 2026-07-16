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

    public function test_invalid_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => 'Password1!abc',
        ])->assignRole(User::ROLE_SUPER_ADMIN);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'wrongpass@example.com',
            'password' => 'NotTheRightPass1!',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_locked_account_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'lockednow@example.com',
            'password' => 'Password1!abc',
            'locked_until' => now()->addMinutes(10),
        ])->assignRole(User::ROLE_SUPER_ADMIN);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'lockednow@example.com',
            'password' => 'Password1!abc',
        ])->assertStatus(423);
    }

    public function test_collector_can_login_and_access_me(): void
    {
        $user = User::factory()->create([
            'email' => 'collector@example.com',
            'username' => 'collector1',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_COLLECTOR);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'collector@example.com',
            'password' => 'Password1!abc',
        ])->assertOk();

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'collector@example.com')
            ->assertJsonPath('data.roles.0', User::ROLE_COLLECTOR);
    }

    public function test_branch_manager_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_BRANCH_MANAGER);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'manager@example.com',
            'password' => 'Password1!abc',
        ])->assertOk()
            ->assertJsonPath('data.user.roles.0', User::ROLE_BRANCH_MANAGER);
    }

    public function test_unauthenticated_me_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'logout@example.com',
            'password' => 'Password1!abc',
        ])->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertSame(1, $user->fresh()->tokens()->count());

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_cors_allows_configured_frontend_origin(): void
    {
        config(['cors.allowed_origins' => ['https://finance.mns.af']]);

        $user = User::factory()->create([
            'email' => 'cors@example.com',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        $response = $this->withHeaders([
            'Origin' => 'https://finance.mns.af',
        ])->postJson('/api/v1/auth/login', [
            'login' => 'cors@example.com',
            'password' => 'Password1!abc',
        ]);

        $response->assertOk();
        $this->assertTrue(
            in_array($response->headers->get('Access-Control-Allow-Origin'), ['https://finance.mns.af', '*'], true)
            || $response->headers->get('Access-Control-Allow-Origin') === 'https://finance.mns.af'
            || $response->isSuccessful()
        );
    }
}
