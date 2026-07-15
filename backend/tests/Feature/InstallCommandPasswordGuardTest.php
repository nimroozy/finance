<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandPasswordGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_does_not_overwrite_existing_admin_password(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@finance.mns.af',
            'username' => 'admin',
            'password' => 'OriginalPass1!xyz',
            'status' => User::STATUS_ACTIVE,
            'force_password_change' => false,
        ]);
        $admin->syncRoles([User::ROLE_SUPER_ADMIN]);
        $originalHash = $admin->password;

        SystemSetting::setValue('setup_completed', 'true');

        $exit = Artisan::call('app:install', [
            '--name' => 'Super Administrator',
            '--email' => 'admin@finance.mns.af',
            '--username' => 'admin',
            '--password' => 'DifferentPass1!xyz',
            '--company' => 'Test Co',
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $admin->refresh();
        $this->assertSame($originalHash, $admin->password);
        $this->assertTrue(Hash::check('OriginalPass1!xyz', $admin->password));
        $this->assertFalse($admin->force_password_change);
    }

    public function test_install_reset_password_requires_explicit_flag(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@finance.mns.af',
            'username' => 'admin',
            'password' => 'OriginalPass1!xyz',
            'status' => User::STATUS_ACTIVE,
            'force_password_change' => false,
        ]);
        $admin->syncRoles([User::ROLE_SUPER_ADMIN]);

        $exit = Artisan::call('app:install', [
            '--name' => 'Super Administrator',
            '--email' => 'admin@finance.mns.af',
            '--username' => 'admin',
            '--password' => 'RotatedPass1!xyz',
            '--company' => 'Test Co',
            '--force' => true,
            '--reset-password' => true,
        ]);

        $this->assertSame(0, $exit);
        $admin->refresh();
        $this->assertTrue(Hash::check('RotatedPass1!xyz', $admin->password));
        $this->assertTrue($admin->force_password_change);
    }

    public function test_install_skips_when_super_admin_already_exists(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::query()->create([
            'name' => 'Super Administrator',
            'email' => 'admin@finance.mns.af',
            'username' => 'admin',
            'password' => 'OriginalPass1!xyz',
            'status' => User::STATUS_ACTIVE,
        ]);
        $admin->syncRoles([User::ROLE_SUPER_ADMIN]);
        $hash = $admin->password;

        $exit = Artisan::call('app:install', [
            '--name' => 'Super Administrator',
            '--email' => 'admin@finance.mns.af',
            '--username' => 'admin',
            '--password' => 'ShouldNotApply1!x',
            '--company' => 'Test Co',
        ]);

        $this->assertSame(0, $exit);
        $admin->refresh();
        $this->assertSame($hash, $admin->password);
        $this->assertSame('true', SystemSetting::getValue('setup_completed'));
    }
}
