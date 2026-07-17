<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\DeploymentInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(DeploymentInfo $deployment): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = collect($checks)->every(fn ($status) => $status === 'ok');

        return ApiResponse::success([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'deployment' => [
                'app_name' => $deployment->toArray()['app_name'],
                'commit_sha' => $deployment->commitSha(),
                'backend_version' => (string) config('app.version', '7.1'),
                'frontend_version' => env('FRONTEND_BUILD_ID') ?: null,
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ], null, $healthy ? 200 : 503);
    }

    protected function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    protected function checkRedis(): string
    {
        try {
            Redis::connection()->ping();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }
}
