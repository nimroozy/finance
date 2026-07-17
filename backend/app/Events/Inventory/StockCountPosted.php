<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockCountPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $stockCountId,
        public int $branchId,
    ) {}
}
