<?php

namespace App\Services\Zoho;

use App\Models\ZohoCircuitBreaker as BreakerModel;
use Throwable;

class ZohoCircuitBreaker
{
    public function allows(string $syncType): bool
    {
        $breaker = BreakerModel::query()->firstOrCreate(
            ['sync_type' => $syncType],
            ['state' => 'closed', 'failure_count' => 0]
        );

        if ($breaker->state !== 'open') {
            return true;
        }
        if ($breaker->resume_after?->isFuture()) {
            return false;
        }

        $breaker->update(['state' => 'half_open']);

        return true;
    }

    public function recordFailure(string $syncType, Throwable|string $error): BreakerModel
    {
        $errorClass = ZohoErrorClassifier::classify($error);
        $breaker = BreakerModel::query()->firstOrCreate(
            ['sync_type' => $syncType],
            ['state' => 'closed', 'failure_count' => 0]
        );
        $failures = $breaker->failure_count + 1;
        $threshold = max(1, (int) config('zoho.sync.circuit_breaker_threshold', 3));
        $permanent = in_array($errorClass, ZohoErrorClassifier::PERMANENT, true);
        $open = $permanent && $failures >= $threshold;

        $breaker->update([
            'failure_count' => $failures,
            'last_error_class' => $errorClass,
            'last_error_message' => $error instanceof Throwable ? $error->getMessage() : $error,
            'state' => $open ? 'open' : ($breaker->state ?: 'closed'),
            'opened_at' => $open ? now() : $breaker->opened_at,
            'resume_after' => $open
                ? now()->addMinutes((int) config('zoho.sync.circuit_breaker_cooldown_minutes', 30))
                : $breaker->resume_after,
        ]);

        return $breaker->fresh();
    }

    public function recordSuccess(string $syncType): void
    {
        BreakerModel::query()->updateOrCreate(
            ['sync_type' => $syncType],
            [
                'state' => 'closed', 'failure_count' => 0, 'last_error_class' => null,
                'last_error_message' => null, 'opened_at' => null, 'resume_after' => null,
            ]
        );
    }

    public function resume(string $syncType): BreakerModel
    {
        $this->recordSuccess($syncType);

        return BreakerModel::query()->where('sync_type', $syncType)->firstOrFail();
    }
}
