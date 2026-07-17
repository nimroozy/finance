<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class DeploymentInfo
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $gitSha = $this->commitSha();

        return [
            'app_name' => (string) config('app.name'),
            'stage' => (string) config('app.stage', '10.1-integrated-stable'),
            'git_sha' => $gitSha,
            'commit_sha' => $gitSha,
            'branch' => $this->branch(),
            'build_timestamp' => $this->buildTimestamp(),
            'deployment_timestamp' => $this->deploymentTimestamp(),
            'backend_version' => (string) config('app.version', '7.1'),
            'frontend_version' => env('FRONTEND_BUILD_ID') ?: null,
            'migration_batch' => $this->migrationBatch(),
            'latest_migration' => $this->latestMigration(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    public function stage(): string
    {
        return (string) config('app.stage', '10.1-integrated-stable');
    }

    public function commitSha(): ?string
    {
        $fromEnv = env('APP_COMMIT_SHA');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return trim($fromEnv);
        }

        $deployedShaPath = base_path('.deployed-sha');
        if (File::isFile($deployedShaPath)) {
            $sha = trim((string) File::get($deployedShaPath));
            if ($sha !== '') {
                return $sha;
            }
        }

        try {
            $root = base_path();
            $output = [];
            $code = 0;
            @exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null', $output, $code);
            if ($code === 0 && isset($output[0]) && is_string($output[0]) && $output[0] !== '') {
                return trim($output[0]);
            }
        } catch (Throwable) {
            // ignore
        }

        // Workspace may keep .git at the monorepo root while Laravel lives in /backend.
        try {
            $root = dirname(base_path());
            $output = [];
            $code = 0;
            @exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null', $output, $code);
            if ($code === 0 && isset($output[0]) && is_string($output[0]) && $output[0] !== '') {
                return trim($output[0]);
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }

    public function branch(): ?string
    {
        $fromEnv = env('APP_BRANCH');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return trim($fromEnv);
        }

        foreach ([base_path(), dirname(base_path())] as $root) {
            try {
                $output = [];
                $code = 0;
                @exec('git -C '.escapeshellarg($root).' rev-parse --abbrev-ref HEAD 2>/dev/null', $output, $code);
                if ($code === 0 && isset($output[0]) && is_string($output[0]) && $output[0] !== '' && $output[0] !== 'HEAD') {
                    return trim($output[0]);
                }
            } catch (Throwable) {
                // ignore
            }
        }

        return 'cursor/stage-10-1-integrated-stable';
    }

    public function buildTimestamp(): ?string
    {
        $fromEnv = env('APP_BUILD_TIMESTAMP');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $deployedShaPath = base_path('.deployed-sha');
        if (File::isFile($deployedShaPath)) {
            $mtime = File::lastModified($deployedShaPath);

            return $mtime ? date('c', $mtime) : null;
        }

        return null;
    }

    public function deploymentTimestamp(): ?string
    {
        $fromEnv = env('APP_DEPLOYMENT_TIMESTAMP');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $deployedAtPath = base_path('.deployed-at');
        if (File::isFile($deployedAtPath)) {
            $raw = trim((string) File::get($deployedAtPath));
            if ($raw !== '') {
                return $raw;
            }
        }

        // Fall back to build/deploy artifact mtime when no explicit deploy stamp exists.
        return $this->buildTimestamp();
    }

    public function migrationBatch(): ?int
    {
        try {
            $batch = DB::table('migrations')->max('batch');

            return $batch !== null ? (int) $batch : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function latestMigration(): ?string
    {
        try {
            $row = DB::table('migrations')
                ->orderByDesc('batch')
                ->orderByDesc('id')
                ->first();

            return $row->migration ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
