<?php

namespace App\Jobs;

use App\Services\Inventory\LowStockAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateInventoryAlertsJob implements ShouldQueue
{
    use Queueable;

    public function handle(LowStockAlertService $alerts): void
    {
        $alerts->evaluate();
    }
}
