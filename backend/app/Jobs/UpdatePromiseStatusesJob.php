<?php

namespace App\Jobs;

use App\Services\Promises\PromiseToPayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdatePromiseStatusesJob implements ShouldQueue
{
    use Queueable;

    public function handle(PromiseToPayService $promises): void
    {
        $promises->updateStatusesByDate();
    }
}
