<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $taskId, public int $branchId,
    ) {}
}
