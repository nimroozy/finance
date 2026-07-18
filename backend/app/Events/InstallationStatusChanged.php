<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallationStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $installationId, public int $branchId, public string $fromStatus, public string $toStatus,
    ) {}
}
