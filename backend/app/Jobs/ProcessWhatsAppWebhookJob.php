<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $payload) {}

    public function handle(WhatsAppWebhookService $webhooks): void
    {
        $webhooks->process($this->payload);
    }
}
