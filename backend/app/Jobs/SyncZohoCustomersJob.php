<?php

namespace App\Jobs;

use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoCircuitBreaker;
use App\Services\Zoho\ZohoCustomerSyncService;
use App\Services\Zoho\ZohoErrorClassifier;
use App\Services\Zoho\ZohoSyncHeartbeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncZohoCustomersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public ?int $syncJobId = null,
        public bool $incremental = true,
        public ?int $triggeredBy = null,
        public ?int $parentJobId = null,
    ) {}

    public function handle(
        ZohoCustomerSyncService $sync,
        ?ZohoCircuitBreaker $circuit = null,
        ?ZohoSyncHeartbeatService $heartbeat = null,
    ): void {
        $circuit ??= app(ZohoCircuitBreaker::class);
        $heartbeat ??= app(ZohoSyncHeartbeatService::class);
        $job = $this->resolveSyncJob();
        if (! $circuit->allows('customers')) {
            $job->markFailed('Zoho customers circuit breaker is open.', 'circuit_open', true);

            return;
        }
        $job->markRunning();
        $heartbeat->touch('queue_worker', 'running', ['job' => 'customers']);
        $heartbeat->recordStart('customer_sync', ['job_id' => $job->id]);

        try {
            $stats = $this->incremental
                ? $sync->syncIncremental($job)
                : $sync->syncFull($job);

            // Never mark complete if failed without entity persistence — stats track this.
            $job->markCompleted($stats);
            $circuit->recordSuccess('customers');
            $heartbeat->recordSuccess('customer_sync', $stats);
        } catch (Throwable $e) {
            $errorClass = ZohoErrorClassifier::classify($e);
            $breaker = $circuit->recordFailure('customers', $e);
            $job->markFailed($e->getMessage(), $errorClass, $breaker->state === 'open');
            $heartbeat->recordFailure('customer_sync', $e);

            // Scheduled runs before OAuth connects should not poison the failed-job queue.
            if (str_contains($e->getMessage(), 'Zoho is not connected')
                || in_array($errorClass, ZohoErrorClassifier::PERMANENT, true)) {
                return;
            }

            throw $e;
        }
    }

    protected function resolveSyncJob(): ZohoSyncJob
    {
        if ($this->syncJobId) {
            return ZohoSyncJob::query()->findOrFail($this->syncJobId);
        }

        return ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_CUSTOMERS,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => $this->triggeredBy,
            'parent_job_id' => $this->parentJobId,
        ]);
    }
}
