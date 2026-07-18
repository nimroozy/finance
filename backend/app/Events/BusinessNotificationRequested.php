<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusinessNotificationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $eventType,
        public array $payload,
        public array $channels = ['in_app', 'whatsapp'],
    ) {}
}
