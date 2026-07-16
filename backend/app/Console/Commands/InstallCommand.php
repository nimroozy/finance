<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InstallCommand extends Command
{
    protected $signature = 'app:install
                            {--name= : Super admin full name}
                            {--email= : Super admin email}
                            {--username= : Super admin username}
                            {--password= : Super admin password (only applied for new users, or with --reset-password)}
                            {--company= : Company name}
                            {--force : Re-run even if setup_completed is true}
                            {--reset-password : Explicitly overwrite an existing admin password (requires --password)}';

    protected $description = 'Install the application: seed roles and create the Super Administrator';

    public function handle(): int
    {
        $superAdminExists = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', User::ROLE_SUPER_ADMIN))
            ->exists();

        if ($superAdminExists && ! $this->option('force') && ! $this->option('reset-password')) {
            $this->warn('Super Administrator already exists. Skipping install (password unchanged).');
            SystemSetting::setValue('setup_completed', 'true');

            return self::SUCCESS;
        }

        if (SystemSetting::getValue('setup_completed') === 'true' && ! $this->option('force') && ! $this->option('reset-password')) {
            $this->error('Setup already completed. Use --force to re-run (will not reset password unless --reset-password).');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Super admin name');
        $email = $this->option('email') ?: $this->ask('Super admin email');
        $username = $this->option('username') ?: $this->ask('Super admin username');
        $password = $this->option('password') ?: $this->secret('Super admin password');
        $company = $this->option('company') ?: $this->ask('Company name', 'Finance Collection');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'company' => $company,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            'company' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $existing = User::query()->where('email', $email)->first();
        $attributes = [
            'name' => $name,
            'username' => $username,
            'status' => User::STATUS_ACTIVE,
            'locale' => 'en',
        ];

        // Never overwrite an existing password unless explicitly requested.
        if (! $existing || $this->option('reset-password')) {
            if ($this->option('reset-password') && $existing) {
                $this->warn('Explicit --reset-password: overwriting existing admin password and forcing password change.');
            }
            $attributes['password'] = $password;
            $attributes['force_password_change'] = true;
        } else {
            $this->warn('Existing admin user found — password left unchanged (use --reset-password to override).');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            $attributes
        );

        $user->syncRoles([User::ROLE_SUPER_ADMIN]);

        SystemSetting::setValue('company_name', $company);
        SystemSetting::setValue('currency', 'AFN');
        SystemSetting::setValue('timezone', 'Asia/Kabul');
        SystemSetting::setValue('setup_completed', 'true');

        $this->info('Installation complete.');
        $this->line("Super admin: {$email} ({$username})");
        if (! empty($attributes['force_password_change'])) {
            $this->warn('force_password_change is enabled for the admin account.');
        }

        return self::SUCCESS;
    }
}
