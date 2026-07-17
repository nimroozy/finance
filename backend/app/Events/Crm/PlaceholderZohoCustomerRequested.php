<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaceholderZohoCustomerRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $leadId,
        public int $customerId,
        public int $branchId,
    ) {}
}
