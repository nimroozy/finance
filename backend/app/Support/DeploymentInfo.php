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
        return [
            'app_name' => (string) config('app.name'),
            'commit_sha' => $this->commitSha(),
            'build_timestamp' => $this->buildTimestamp(),
            'backend_version' => (string) config('app.version', '7.1'),
            'frontend_version' => env('FRONTEND_BUILD_ID') ?: null,
            'migration_batch' => $this->migrationBatch(),
            'latest_migration' => $this->latestMigration(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
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

        return null;
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
