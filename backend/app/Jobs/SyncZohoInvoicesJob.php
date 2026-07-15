<?php

namespace App\Jobs;

use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoInvoiceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncZohoInvoicesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $syncJobId = null,
        public bool $incremental = true,
        public ?int $triggeredBy = null,
        public ?int $parentJobId = null,
    ) {}

    public function handle(ZohoInvoiceSyncService $sync): void
    {
        $job = $this->resolveSyncJob();
        $job->markRunning();

        try {
            $stats = $this->incremental
                ? $sync->syncIncremental($job)
                : $sync->syncFull($job);

            $job->markCompleted($stats);
        } catch (Throwable $e) {
            $job->markFailed($e->getMessage());
            throw $e;
        }
    }

    protected function resolveSyncJob(): ZohoSyncJob
    {
        if ($this->syncJobId) {
            return ZohoSyncJob::query()->findOrFail($this->syncJobId);
        }

        return ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_INVOICES,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => $this->triggeredBy,
            'parent_job_id' => $this->parentJobId,
        ]);
    }
}
