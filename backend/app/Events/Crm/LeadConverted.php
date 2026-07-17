<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadConverted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $leadId,
        public int $branchId,
        public int $customerId,
        public int $installationId,
    ) {}
}
