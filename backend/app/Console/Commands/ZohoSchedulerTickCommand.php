<?php

namespace App\Console\Commands;

use App\Jobs\RefreshZohoTokenJob;
use App\Jobs\RetryFailedZohoSyncJob;
use App\Jobs\SyncZohoCustomersJob;
use App\Jobs\SyncZohoInvoicesJob;
use App\Jobs\SyncZohoOrganizationStructureJob;
use App\Models\ZohoSyncHeartbeat;
use App\Services\Zoho\ZohoConfig;
use App\Services\Zoho\ZohoSyncHeartbeatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ZohoSchedulerTickCommand extends Command
{
    protected $signature = 'zoho:scheduler-tick';

    protected $description = 'Dispatch due Zoho maintenance and synchronization jobs';

    public function handle(ZohoConfig $config, ZohoSyncHeartbeatService $heartbeat): int
    {
        $heartbeat->touch('scheduler', 'running');
        $definitions = [
            'customer_sync' => ['customer_sync_minutes', fn () => SyncZohoCustomersJob::dispatch(null, true)],
            'invoice_sync' => ['invoice_sync_minutes', fn () => SyncZohoInvoicesJob::dispatch(null, true)],
            'token_refresh' => ['token_refresh_minutes', fn () => RefreshZohoTokenJob::dispatch()],
            'retry' => ['retry_failed_minutes', fn () => RetryFailedZohoSyncJob::dispatch()],
            'structure_sync' => ['structure_sync_minutes', fn () => SyncZohoOrganizationStructureJob::dispatch()],
        ];

        foreach ($definitions as $component => [$setting, $dispatch]) {
            $minutes = $config->scheduleMinutes($setting);
            $last = ZohoSyncHeartbeat::query()->where('component', $component)->first();
            $reference = $last?->last_completion_at ?? $last?->last_heartbeat_at;
            if ($reference?->gt(now()->subMinutes($minutes))) {
                continue;
            }
            if (! Cache::lock("zoho:scheduler:{$component}", min(900, max(60, $minutes * 60)))->get()) {
                continue;
            }
            $dispatch();
            ZohoSyncHeartbeat::query()->updateOrCreate(['component' => $component], [
                'status' => 'queued', 'last_heartbeat_at' => now(), 'next_expected_at' => now()->addMinutes($minutes),
            ]);
        }

        $hour = (int) config('zoho.schedule.nightly_full_hour_utc', 2);
        if (now('UTC')->hour === $hour) {
            if (config('zoho.schedule.full_customer_daily', true)
                && Cache::lock('zoho:scheduler:customers-full:'.now('UTC')->toDateString(), 7200)->get()) {
                SyncZohoCustomersJob::dispatch(null, false);
            }
            if (config('zoho.schedule.full_invoice_daily', true)
                && Cache::lock('zoho:scheduler:invoices-full:'.now('UTC')->toDateString(), 7200)->get()) {
                SyncZohoInvoicesJob::dispatch(null, false);
            }
        }

        $heartbeat->touch('scheduler', 'success');

        return self::SUCCESS;
    }
}
