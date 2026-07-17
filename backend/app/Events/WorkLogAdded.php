<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkLogAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $workLogId, public int $branchId, public ?int $ticketId, public ?int $taskId,
    ) {}
}
