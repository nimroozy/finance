<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_successful_login_creates_audit_entry(): void
    {
        $user = User::factory()->create([
            'email' => 'audit@example.com',
            'password' => 'Password1!abc',
        ]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'audit@example.com',
            'password' => 'Password1!abc',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_success',
            'user_id' => $user->id,
        ]);

        $log = AuditLog::query()->where('action', 'auth.login_success')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
    }

    public function test_failed_login_creates_audit_entry(): void
    {
        $user = User::factory()->create([
            'email' => 'fail@example.com',
            'password' => 'Password1!abc',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'fail@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
            'user_id' => $user->id,
        ]);
    }
}
