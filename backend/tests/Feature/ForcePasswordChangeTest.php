<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_must_change_password_before_accessing_protected_routes(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Password change required before continuing.');

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }

    public function test_user_can_logout_and_change_password_when_forced(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!abc',
            'password' => 'NewPassword2!xyz',
            'password_confirmation' => 'NewPassword2!xyz',
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->force_password_change);

        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password1!abc',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);
    }
}
