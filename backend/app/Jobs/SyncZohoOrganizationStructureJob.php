<?php

namespace App\Jobs;

use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoCircuitBreaker;
use App\Services\Zoho\ZohoErrorClassifier;
use App\Services\Zoho\ZohoOrganizationStructureSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncZohoOrganizationStructureJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ?int $syncJobId = null) {}

    public function handle(ZohoOrganizationStructureSyncService $sync, ZohoCircuitBreaker $circuit): void
    {
        $job = $this->syncJobId
            ? ZohoSyncJob::query()->findOrFail($this->syncJobId)
            : ZohoSyncJob::query()->create(['type' => ZohoSyncJob::TYPE_STRUCTURE, 'status' => ZohoSyncJob::STATUS_PENDING]);
        if (! $circuit->allows('structure')) {
            $job->markFailed('Zoho structure circuit breaker is open.', 'circuit_open', true);

            return;
        }

        $job->markRunning();
        try {
            $stats = $sync->sync();
            $job->markCompleted($stats);
            $circuit->recordSuccess('structure');
        } catch (Throwable $e) {
            $errorClass = ZohoErrorClassifier::classify($e);
            $breaker = $circuit->recordFailure('structure', $e);
            $job->markFailed($e->getMessage(), $errorClass, $breaker->state === 'open');
            if (! in_array($errorClass, ZohoErrorClassifier::PERMANENT, true)) {
                throw $e;
            }
        }
    }
}
