<?php

namespace App\Jobs;

use App\Services\Sla\SlaClockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateSlaBreachesJob implements ShouldQueue
{
    use Queueable;

    public function handle(SlaClockService $sla): void
    {
        $sla->evaluateBreaches();
    }
}
