<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentInstalled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $equipmentId,
        public int $branchId,
        public int $installationId,
    ) {}
}
