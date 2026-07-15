<?php

namespace App\Jobs;

use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPaymentReconciliationJob implements ShouldQueue
{
    use Queueable;

    public function handle(PaymentReconciliationService $service): void
    {
        if (! config('payments.reconciliation.daily_enabled', true)) {
            return;
        }

        $service->runDailyAllBranches();
    }
}
