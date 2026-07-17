<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $leadId,
        public int $branchId,
        public string $fromStage,
        public string $toStage,
    ) {}
}
