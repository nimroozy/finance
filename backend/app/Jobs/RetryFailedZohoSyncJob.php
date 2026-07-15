<?php

namespace App\Jobs;

use App\Models\ZohoSyncJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedZohoSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $syncJobId = null) {}

    public function handle(): void
    {
        if ($this->syncJobId) {
            $this->retryOne(ZohoSyncJob::query()->findOrFail($this->syncJobId));

            return;
        }

        $failed = ZohoSyncJob::query()
            ->whereIn('status', [
                ZohoSyncJob::STATUS_FAILED,
                ZohoSyncJob::STATUS_PARTIALLY_COMPLETED,
            ])
            ->whereIn('type', [
                ZohoSyncJob::TYPE_CUSTOMERS,
                ZohoSyncJob::TYPE_INVOICES,
                ZohoSyncJob::TYPE_FULL,
                ZohoSyncJob::TYPE_TOKEN_REFRESH,
            ])
            ->where('updated_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($failed as $job) {
            $this->retryOne($job);
        }
    }

    protected function retryOne(ZohoSyncJob $job): void
    {
        $job->update([
            'status' => ZohoSyncJob::STATUS_RETRYING,
            'error_message' => null,
            'finished_at' => null,
        ]);

        if ($job->type === ZohoSyncJob::TYPE_FULL) {
            $this->retryFull($job);

            return;
        }

        $child = ZohoSyncJob::query()->create([
            'type' => $job->type,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => $job->triggered_by,
            'parent_job_id' => $job->id,
        ]);

        match ($job->type) {
            ZohoSyncJob::TYPE_CUSTOMERS => SyncZohoCustomersJob::dispatch($child->id, true, $job->triggered_by, $job->id),
            ZohoSyncJob::TYPE_INVOICES => SyncZohoInvoicesJob::dispatch($child->id, true, $job->triggered_by, $job->id),
            ZohoSyncJob::TYPE_TOKEN_REFRESH => RefreshZohoTokenJob::dispatch($child->id),
            default => $job->markFailed('Unsupported sync type for retry: '.$job->type),
        };
    }

    protected function retryFull(ZohoSyncJob $job): void
    {
        $customers = ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_CUSTOMERS,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => $job->triggered_by,
            'parent_job_id' => $job->id,
        ]);
        $invoices = ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_INVOICES,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => $job->triggered_by,
            'parent_job_id' => $job->id,
        ]);

        SyncZohoCustomersJob::dispatch($customers->id, true, $job->triggered_by, $job->id);
        SyncZohoInvoicesJob::dispatch($invoices->id, true, $job->triggered_by, $job->id);
    }
}
