<?php

namespace App\Jobs;

use App\Models\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 300, 900];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppMessageService $messages): void
    {
        $message = $messages->send($this->messageId);
        if ($message->status === 'failed') {
            $this->delete();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = WhatsAppMessage::find($this->messageId);
        if (! $message || $message->status === 'failed') {
            return;
        }

        app(WhatsAppMessageService::class)->markExhaustedRetries(
            $message,
            $exception?->getMessage(),
        );
    }
}
