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
                            {--password= : Super admin password}
                            {--company= : Company name}
                            {--force : Re-run even if setup_completed is true}';

    protected $description = 'Install the application: seed roles and create the Super Administrator';

    public function handle(): int
    {
        if (SystemSetting::getValue('setup_completed') === 'true' && ! $this->option('force')) {
            $this->error('Setup already completed. Use --force to re-run.');

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

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => true,
                'locale' => 'en',
            ]
        );

        $user->syncRoles([User::ROLE_SUPER_ADMIN]);

        SystemSetting::setValue('company_name', $company);
        SystemSetting::setValue('currency', 'AFN');
        SystemSetting::setValue('timezone', 'Asia/Kabul');
        SystemSetting::setValue('setup_completed', 'true');

        $this->info('Installation complete.');
        $this->line("Super admin: {$email} ({$username})");
        $this->warn('force_password_change is enabled for the admin account.');

        return self::SUCCESS;
    }
}
