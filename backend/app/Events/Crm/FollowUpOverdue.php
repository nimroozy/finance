<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FollowUpOverdue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $followUpId,
        public int $branchId,
    ) {}
}
