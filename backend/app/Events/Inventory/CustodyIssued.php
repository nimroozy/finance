<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustodyIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $custodyId,
        public int $branchId,
    ) {}
}
