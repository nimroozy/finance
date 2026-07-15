<?php

use App\Jobs\RefreshZohoTokenJob;
use App\Jobs\RetryFailedZohoSyncJob;
use App\Jobs\SyncZohoCustomersJob;
use App\Jobs\SyncZohoInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$applyInterval = function ($event, int $minutes) {
    if ($minutes >= 60) {
        return $event->hourly();
    }

    if ($minutes === 30) {
        return $event->everyThirtyMinutes();
    }

    if ($minutes === 15) {
        return $event->everyFifteenMinutes();
    }

    return $event->cron(sprintf('*/%d * * * *', min($minutes, 59)));
};

// Intervals from config/zoho.php (overridable via env). Runtime overrides via
// system_settings keys are read inside ZohoConfig::scheduleMinutes() by jobs if needed.
$customerMinutes = max(1, (int) config('zoho.schedule.customer_sync_minutes', 60));
$invoiceMinutes = max(1, (int) config('zoho.schedule.invoice_sync_minutes', 60));
$tokenMinutes = max(1, (int) config('zoho.schedule.token_refresh_minutes', 30));
$retryMinutes = max(1, (int) config('zoho.schedule.retry_failed_minutes', 15));

$applyInterval(
    Schedule::job(new SyncZohoCustomersJob(null, true))->name('zoho-sync-customers')->withoutOverlapping(),
    $customerMinutes
);

$applyInterval(
    Schedule::job(new SyncZohoInvoicesJob(null, true))->name('zoho-sync-invoices')->withoutOverlapping(),
    $invoiceMinutes
);

$applyInterval(
    Schedule::job(new RefreshZohoTokenJob)->name('zoho-refresh-token')->withoutOverlapping(),
    $tokenMinutes
);

$applyInterval(
    Schedule::job(new RetryFailedZohoSyncJob)->name('zoho-retry-failed')->withoutOverlapping(),
    $retryMinutes
);
