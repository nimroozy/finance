<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AcceptanceResetCommand extends Command
{
    protected $signature = 'acceptance:reset {--force : Required confirmation flag}';

    protected $description = 'Reset acceptance database (migrate:fresh + permission + AcceptanceSeeder). Blocked in production.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('acceptance:reset refused: APP_ENV=production');

            return self::FAILURE;
        }

        if (! app()->environment('acceptance', 'testing', 'local')) {
            $this->error('acceptance:reset requires APP_ENV=acceptance|testing|local');

            return self::FAILURE;
        }

        $db = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_contains(strtolower($db), 'acceptance') && ! app()->environment('testing')) {
            $this->error("Refusing reset: database name '{$db}' does not contain 'acceptance'");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Pass --force to reset acceptance database');

            return self::FAILURE;
        }

        $this->warn("Resetting acceptance database: {$db}");
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->line(Artisan::output());
        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
        $this->line(Artisan::output());
        Artisan::call('db:seed', ['--class' => 'AcceptanceSeeder', '--force' => true]);
        $this->line(Artisan::output());

        $this->info('Acceptance database reset complete.');
        $this->line(json_encode([
            'database' => $db,
            'users' => DB::table('users')->where('username', 'like', 'ACCEPTANCE-%')->count(),
        ]));

        return self::SUCCESS;
    }
}
