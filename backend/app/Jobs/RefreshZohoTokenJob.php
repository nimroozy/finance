<?php

namespace App\Jobs;

use App\Models\ZohoConnection;
use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoTokenService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshZohoTokenJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $syncJobId = null) {}

    public function handle(ZohoTokenService $tokens): void
    {
        $syncJob = $this->resolveSyncJob();
        $syncJob?->markRunning();

        $connection = ZohoConnection::current();

        if (! $connection || ! $connection->refresh_token) {
            $syncJob?->markCompleted(['processed' => 0]);

            return;
        }

        if (! $connection->tokenNeedsRefresh()) {
            $syncJob?->markCompleted(['processed' => 0]);

            return;
        }

        try {
            $tokens->refresh($connection);
            $syncJob?->markCompleted(['processed' => 1, 'updated' => 1]);
        } catch (Throwable $e) {
            $syncJob?->markFailed($e->getMessage());
            throw $e;
        }
    }

    protected function resolveSyncJob(): ?ZohoSyncJob
    {
        if ($this->syncJobId) {
            return ZohoSyncJob::query()->find($this->syncJobId);
        }

        return ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_TOKEN_REFRESH,
            'status' => ZohoSyncJob::STATUS_PENDING,
        ]);
    }
}
