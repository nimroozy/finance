<?php

namespace App\Jobs;

use App\Services\Sla\EscalationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateEscalationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(EscalationService $escalations): void
    {
        $escalations->evaluate();
    }
}
