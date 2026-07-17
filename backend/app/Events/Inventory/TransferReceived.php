<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $transferId,
        public int $branchId,
    ) {}
}
