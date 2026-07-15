<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'admin:reset-password
                            {login : Admin email or username}
                            {--password= : New password (prefer interactive prompt or --password-file)}
                            {--password-file= : Read password from a file (contents trimmed, file not echoed)}
                            {--force-change : Require password change on next login}
                            {--unlock : Clear lock and failed login attempts}';

    protected $description = 'Securely reset an administrator (or any user) password without logging the secret';

    public function handle(AuditLogger $audit): int
    {
        $login = (string) $this->argument('login');

        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('email', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if (! $user->isSuperAdmin() && ! $this->confirm('User is not a Super Administrator. Continue anyway?', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $updates = [
            'password' => $password,
            'force_password_change' => (bool) $this->option('force-change'),
        ];

        if ($this->option('unlock') || $user->isLocked() || $user->failed_login_attempts > 0) {
            $updates['locked_until'] = null;
            $updates['failed_login_attempts'] = 0;
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            if ($this->confirm('Account is not active. Activate now?', true)) {
                $updates['status'] = User::STATUS_ACTIVE;
            }
        }

        $user->forceFill($updates)->save();
        $user->tokens()->delete();

        $audit->log('auth.admin_password_reset', $user, null, [
            'target_user_id' => $user->id,
            'force_password_change' => (bool) $user->force_password_change,
            'unlocked' => array_key_exists('locked_until', $updates),
        ], null, 'Password reset via artisan admin:reset-password');

        $this->info('Password updated for user #'.$user->id.' ('.$user->email.').');
        $this->line('Existing API tokens were revoked.');
        $this->line('Password value was not written to output or audit details.');

        return self::SUCCESS;
    }

    protected function resolvePassword(): ?string
    {
        $file = $this->option('password-file');
        if (is_string($file) && $file !== '') {
            if (! is_readable($file)) {
                $this->error('Password file is not readable.');

                return null;
            }

            $contents = file_get_contents($file);
            if ($contents === false) {
                $this->error('Unable to read password file.');

                return null;
            }

            return trim($contents);
        }

        $optionPassword = $this->option('password');
        if (is_string($optionPassword) && $optionPassword !== '') {
            $this->warn('Passing --password on the CLI may expose the secret in shell history. Prefer --password-file or interactive prompt.');

            return $optionPassword;
        }

        $password = $this->secret('New password');
        $confirm = $this->secret('Confirm password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match.');

            return null;
        }

        return $password;
    }
}
