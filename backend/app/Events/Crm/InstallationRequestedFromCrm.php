<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallationRequestedFromCrm
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $leadId,
        public int $installationId,
        public int $branchId,
    ) {}
}
