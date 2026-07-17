<?php

namespace App\Events\Services;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $serviceId, public int $branchId, public string $reason = '') {}
}
