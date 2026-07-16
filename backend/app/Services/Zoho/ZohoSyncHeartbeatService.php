<?php

namespace App\Services\Zoho;

use App\Models\ZohoSyncHeartbeat;
use Throwable;

class ZohoSyncHeartbeatService
{
    public function touch(string $component, string $status = 'ok', array $meta = []): ZohoSyncHeartbeat
    {
        return ZohoSyncHeartbeat::query()->updateOrCreate(
            ['component' => $component],
            ['status' => $status, 'last_heartbeat_at' => now(), 'meta' => $meta]
        );
    }

    public function recordStart(string $component, array $meta = []): ZohoSyncHeartbeat
    {
        return ZohoSyncHeartbeat::query()->updateOrCreate(
            ['component' => $component],
            ['status' => 'running', 'last_heartbeat_at' => now(), 'last_start_at' => now(), 'last_error' => null, 'meta' => $meta]
        );
    }

    public function recordSuccess(string $component, array $meta = []): ZohoSyncHeartbeat
    {
        $heartbeat = ZohoSyncHeartbeat::query()->firstOrNew(['component' => $component]);
        $duration = $heartbeat->last_start_at ? $heartbeat->last_start_at->diffInMilliseconds(now()) : null;
        $heartbeat->fill([
            'status' => 'success', 'last_heartbeat_at' => now(), 'last_completion_at' => now(),
            'last_duration_ms' => $duration, 'last_error' => null, 'meta' => $meta,
        ])->save();

        return $heartbeat;
    }

    public function recordFailure(string $component, Throwable|string $error, array $meta = []): ZohoSyncHeartbeat
    {
        $heartbeat = ZohoSyncHeartbeat::query()->firstOrNew(['component' => $component]);
        $heartbeat->fill([
            'status' => 'failed', 'last_heartbeat_at' => now(), 'last_completion_at' => now(),
            'last_error' => $error instanceof Throwable ? $error->getMessage() : $error, 'meta' => $meta,
        ])->save();

        return $heartbeat;
    }
}
