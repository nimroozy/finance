<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZohoPurchaseSyncRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $purchaseOrderId,
        public int $branchId,
    ) {}
}
