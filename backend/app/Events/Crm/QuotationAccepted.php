<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $quotationId,
        public int $branchId,
        public int $leadId,
    ) {}
}
