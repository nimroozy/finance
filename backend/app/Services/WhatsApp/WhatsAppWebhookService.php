<?php

namespace App\Services\WhatsApp;

use App\Events\InboundMessageReceived;
use App\Models\Customer;
use App\Models\WhatsApp\WhatsAppConnection;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppInboundMessage;
use App\Models\WhatsApp\WhatsAppOptOut;
use App\Models\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Support\Arr;
use Throwable;

class WhatsAppWebhookService
{
    public function __construct(
        private WhatsAppMessageService $messages,
        private PhoneNumberNormalizer $phones,
    ) {}

    public function verifyChallenge(?string $mode, ?string $token, ?string $challenge): ?string
    {
        if ($mode !== 'subscribe' || ! hash_equals((string) config('whatsapp.verify_token'), (string) $token)) {
            return null;
        }

        return $challenge;
    }

    public function verifySignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('whatsapp.webhook_secret');
        if ($secret === '') {
            return true;
        }

        $expected = 'sha256='.hash_hmac('sha256', $body, $secret);

        return is_string($signature) && hash_equals($expected, $signature);
    }

    public function process(array $payload): int
    {
        $processed = 0;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    $eventId = implode(':', [
                        $status['id'] ?? 'unknown',
                        $status['status'] ?? 'status',
                        $status['timestamp'] ?? '0',
                    ]);
                    if ($this->beginEvent($eventId, 'status', $status)) {
                        try {
                            $this->messages->updateStatus(
                                (string) ($status['id'] ?? ''),
                                (string) ($status['status'] ?? 'unknown'),
                                $status,
                                $eventId,
                            );
                            $this->completeEvent($eventId);
                            $processed++;
                        } catch (Throwable $e) {
                            $this->failEvent($eventId, $e);
                            throw $e;
                        }
                    }
                }
                foreach ($value['messages'] ?? [] as $inbound) {
                    $eventId = (string) ($inbound['id'] ?? hash('sha256', json_encode($inbound)));
                    if ($this->beginEvent($eventId, 'inbound', $inbound)) {
                        try {
                            $this->inbound($inbound);
                            $this->completeEvent($eventId);
                            $processed++;
                        } catch (Throwable $e) {
                            $this->failEvent($eventId, $e);
                            throw $e;
                        }
                    }
                }
            }
        }

        WhatsAppConnection::query()->orderBy('id')->limit(1)->update(['last_webhook_received' => now()]);

        return $processed;
    }

    private function beginEvent(string $eventId, string $type, array $payload): bool
    {
        $event = WhatsAppWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            ['event_type' => $type, 'payload' => $payload, 'status' => 'received'],
        );
        if (! $event->wasRecentlyCreated && in_array($event->status, ['processed', 'processing'], true)) {
            return false;
        }
        if (! $event->wasRecentlyCreated && $event->status === 'failed') {
            return false;
        }

        $event->update(['status' => 'processing', 'error' => null]);

        return true;
    }

    private function completeEvent(string $eventId): void
    {
        WhatsAppWebhookEvent::where('event_id', $eventId)->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    private function failEvent(string $eventId, Throwable $e): void
    {
        WhatsAppWebhookEvent::where('event_id', $eventId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }

    private function inbound(array $message): void
    {
        $rawFrom = (string) ($message['from'] ?? '');
        $phone = $this->phones->normalize($rawFrom);
        $body = Arr::get($message, 'text.body');
        $receivedAt = isset($message['timestamp'])
            ? now()->setTimestamp((int) $message['timestamp'])
            : now();

        $conversation = null;
        if ($phone !== null) {
            $customer = $this->findCustomerByPhone($phone);
            $conversation = WhatsAppConversation::firstOrCreate(
                ['phone_e164' => $phone, 'status' => 'open'],
                [
                    'branch_id' => $customer?->branch_id,
                    'customer_id' => $customer?->id,
                    'last_message_at' => $receivedAt,
                ],
            );
            if ($customer && ! $conversation->customer_id) {
                $conversation->update([
                    'customer_id' => $customer->id,
                    'branch_id' => $customer->branch_id,
                ]);
            }
            $conversation->update(['last_message_at' => $receivedAt]);
        }

        $inboundRecord = WhatsAppInboundMessage::firstOrCreate(
            ['meta_message_id' => $message['id']],
            [
                'conversation_id' => $conversation?->id,
                'from_phone' => $phone ?? $rawFrom,
                'type' => $message['type'] ?? 'text',
                'body' => $body,
                'payload' => array_merge($message, ['raw_from' => $rawFrom]),
                'received_at' => $receivedAt,
            ],
        );

        if ($phone !== null && is_string($body) && in_array(mb_strtoupper(trim($body)), ['STOP', 'UNSUBSCRIBE', 'لغو'], true)) {
            WhatsAppOptOut::firstOrCreate(
                ['phone_e164' => $phone],
                ['source' => 'inbound', 'reason' => $body, 'opted_out_at' => now()],
            );
        }

        // Ticketing intake runs after commit via ShouldQueueAfterCommit listener.
        if ($inboundRecord->wasRecentlyCreated) {
            InboundMessageReceived::dispatch(
                (int) $inboundRecord->id,
                $inboundRecord->conversation_id ? (int) $inboundRecord->conversation_id : null,
            );
        }
    }

    private function findCustomerByPhone(string $phoneE164): ?Customer
    {
        $suffix = substr(preg_replace('/\D/', '', $phoneE164), -9);
        if ($suffix === '') {
            return null;
        }

        $candidates = Customer::query()
            ->where(function ($q) use ($suffix) {
                $q->where('mobile', 'like', '%'.$suffix)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->limit(20)
            ->get();

        foreach ($candidates as $customer) {
            foreach (['mobile', 'phone'] as $field) {
                $value = $customer->{$field};
                if (! is_string($value) || $value === '') {
                    continue;
                }
                if ($this->phones->normalize($value) === $phoneE164) {
                    return $customer;
                }
            }
        }

        return null;
    }
}
