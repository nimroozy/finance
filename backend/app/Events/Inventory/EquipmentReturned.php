<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentReturned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $equipmentId,
        public int $branchId,
    ) {}
}
