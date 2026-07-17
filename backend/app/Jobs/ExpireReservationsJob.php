<?php

namespace App\Jobs;

use App\Services\Inventory\ReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReservationService $reservations): void
    {
        $reservations->expireDue();
    }
}
