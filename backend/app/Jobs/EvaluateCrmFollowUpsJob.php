<?php

namespace App\Jobs;

use App\Services\Crm\FollowUpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateCrmFollowUpsJob implements ShouldQueue
{
    use Queueable;

    public function handle(FollowUpService $followUps): void
    {
        $followUps->evaluateOverdue();
    }
}
