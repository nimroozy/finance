<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockTransactionPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $transactionId,
        public int $branchId,
        public string $type,
    ) {}
}
