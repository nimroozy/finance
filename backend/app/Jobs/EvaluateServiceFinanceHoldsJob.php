<?php

namespace App\Jobs;

use App\Models\Services\ServiceFinanceHold;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateServiceFinanceHoldsJob implements ShouldQueue
{
    use Queueable;

    public function handle(AuditLogger $audit): void
    {
        ServiceFinanceHold::query()
            ->where('status', ServiceFinanceHold::STATUS_ACTIVE)
            ->whereNotNull('review_at')
            ->where('review_at', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (ServiceFinanceHold $hold) use ($audit) {
                $audit->log('service.finance_hold_review_due', $hold->service, null, [
                    'hold_id' => $hold->id,
                    'review_at' => $hold->review_at,
                ], $hold->service?->branch_id);
            });
    }
}
