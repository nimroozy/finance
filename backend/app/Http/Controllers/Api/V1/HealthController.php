<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = collect($checks)->every(fn ($status) => $status === 'ok');

        return ApiResponse::success([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
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
