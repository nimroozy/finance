<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Customer;
use App\Models\WhatsApp\WhatsAppContactPreference;
use App\Models\WhatsApp\WhatsAppFailure;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppMessageEvent;
use App\Models\WhatsApp\WhatsAppMessageRecipient;
use App\Models\WhatsApp\WhatsAppOptOut;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppMessageService
{
    public function __construct(
        private PhoneNumberNormalizer $phones,
        private WhatsAppClient $client,
        private WhatsAppConnectionService $connections,
    ) {}

    public function queueTemplate(
        mixed $phone,
        mixed $template,
        mixed $language = 'fa',
        mixed $components = [],
        mixed $branchId = null,
        mixed $customerId = null,
        mixed $createdBy = null,
        ?string $eventType = null,
    ): WhatsAppMessage {
        if ($template instanceof Customer) {
            $eventType = (string) $phone;
            $customer = $template;
            [$phone, $template, $language, $components, $branchId, $customerId, $createdBy] = [
                $language, $components, $branchId, $customerId ?? [], $createdBy,
                $customer->id, null,
            ];
        }
        $phone = (string) $phone;
        $template = (string) $template;
        $language = (string) $language;
        $components = is_array($components) ? $components : [];
        $branchId = $branchId !== null ? (int) $branchId : null;
        $customerId = $customerId !== null ? (int) $customerId : null;
        $createdBy = $createdBy !== null ? (int) $createdBy : null;

        $normalized = $this->phones->normalize($phone);
        if ($normalized === null) {
            $message = $this->createMessage($template, $language, $components, $branchId, $customerId, $createdBy, 'failed', $eventType);
            $this->recordPermanentFailure($message, 'invalid_number', 'Invalid phone number.');

            return $message->fresh();
        }

        if (! $this->contactPreferencesAllow($normalized, $eventType)) {
            $message = $this->createMessage($template, $language, $components, $branchId, $customerId, $createdBy, 'failed', $eventType, $normalized);
            WhatsAppMessageRecipient::create(['message_id' => $message->id, 'phone_e164' => $normalized]);
            $this->recordPermanentFailure($message, 'preference_blocked', 'Contact preferences block this message.');

            return $message->fresh();
        }

        $message = $this->createMessage($template, $language, $components, $branchId, $customerId, $createdBy, 'queued', $eventType, $normalized);
        WhatsAppMessageRecipient::create(['message_id' => $message->id, 'phone_e164' => $normalized]);

        if (WhatsAppOptOut::query()->where('phone_e164', $normalized)->exists()) {
            $this->recordPermanentFailure($message, 'opted_out', 'Recipient has opted out.');

            return $message->fresh();
        }

        SendWhatsAppMessageJob::dispatch($message->id);

        return $message->fresh();
    }

    public function sendTemplate(...$arguments): WhatsAppMessage
    {
        return $this->queueTemplate(...$arguments);
    }

    public function send(WhatsAppMessage|int $message): WhatsAppMessage
    {
        $message = $message instanceof WhatsAppMessage ? $message : WhatsAppMessage::findOrFail($message);
        if (in_array($message->status, ['sent', 'delivered', 'read', 'cancelled'], true)) {
            return $message;
        }
        if ($message->status === 'failed' && $this->hasPermanentFailure($message)) {
            return $message;
        }

        $connection = $this->connections->connection();
        if ($connection->is_paused || config('whatsapp.outgoing_paused')) {
            return $message;
        }

        $recipient = WhatsAppMessageRecipient::where('message_id', $message->id)->firstOrFail();
        if (WhatsAppOptOut::where('phone_e164', $recipient->phone_e164)->exists()) {
            $this->recordPermanentFailure($message, 'opted_out', 'Recipient has opted out.');

            return $message->fresh();
        }

        if (! $this->contactPreferencesAllow($recipient->phone_e164, $message->event_type)) {
            $this->recordPermanentFailure($message, 'preference_blocked', 'Contact preferences block this message.');

            return $message->fresh();
        }

        try {
            $result = $this->client->sendTemplate(
                $recipient->phone_e164,
                $message->template_name,
                $message->language_code ?: 'fa',
                $message->payload['components'] ?? [],
                $connection,
            );
            $metaId = $result['messages'][0]['id'] ?? null;
            $message->update([
                'status' => 'sent',
                'meta_message_id' => $metaId,
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);
            $recipient->update(['status' => 'sent']);
            $connection->update(['last_successful_api_call' => now()]);
            $this->event($message, 'sent', $result);
        } catch (Throwable $e) {
            $classified = $this->classifyThrowable($e);
            if ($classified['permanent']) {
                $this->recordPermanentFailure($message, $classified['code'], $classified['message']);
            } else {
                $this->recordRetryFailure($message, $classified['code'], $classified['message']);
                throw $e;
            }
        }

        return $message->fresh();
    }

    public function markExhaustedRetries(WhatsAppMessage|int $message, ?string $reason = null): WhatsAppMessage
    {
        $message = $message instanceof WhatsAppMessage ? $message : WhatsAppMessage::findOrFail($message);
        $this->recordPermanentFailure(
            $message,
            'retries_exhausted',
            $reason ?: 'Maximum send attempts exceeded.',
        );

        return $message->fresh();
    }

    public function updateStatus(string $metaMessageId, string $status, array $payload = [], ?string $eventId = null): ?WhatsAppMessage
    {
        $message = WhatsAppMessage::where('meta_message_id', $metaMessageId)->first();
        if (! $message) {
            return null;
        }
        $values = ['status' => $status];
        if (in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            $values[$status.'_at'] = now();
        }
        $message->update($values);
        WhatsAppMessageRecipient::where('message_id', $message->id)->update(['status' => $status]);
        $this->event($message, $status, $payload, $eventId);
        if ($status === 'failed') {
            $error = $payload['errors'][0] ?? $payload['error'] ?? [];
            $this->recordPermanentFailure(
                $message,
                (string) ($error['code'] ?? 'meta_failed'),
                (string) ($error['title'] ?? $error['message'] ?? 'Meta rejected message'),
            );
        }

        return $message->fresh();
    }

    /**
     * @return array{code: string, message: string, permanent: bool}
     */
    public function classifyThrowable(Throwable $e): array
    {
        if ($e instanceof ConnectionException) {
            return ['code' => 'network', 'message' => $e->getMessage(), 'permanent' => false];
        }

        if ($e instanceof RequestException) {
            $response = $e->response;
            $status = $response?->status() ?? 0;
            $body = $response?->json() ?? [];
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];
            $code = (string) ($error['code'] ?? $error['error_subcode'] ?? $status);
            $message = (string) ($error['message'] ?? $error['error_user_msg'] ?? $e->getMessage());
            $haystack = strtolower($code.' '.$message);

            if ($status === 429 || str_contains($haystack, 'rate limit') || $code === '80007' || $code === '4') {
                return ['code' => 'rate_limit', 'message' => $message, 'permanent' => false];
            }
            if ($code === '190' || str_contains($haystack, 'access token') || str_contains($haystack, 'expired')) {
                return ['code' => 'token_expired', 'message' => $message, 'permanent' => true];
            }
            if (str_contains($haystack, 'template') && (str_contains($haystack, 'not found') || str_contains($haystack, 'rejected') || str_contains($haystack, 'disabled'))) {
                return ['code' => 'template_missing', 'message' => $message, 'permanent' => true];
            }
            if (in_array($code, ['131026', '131031', '131009', '133010'], true) || str_contains($haystack, 'invalid') && str_contains($haystack, 'phone')) {
                return ['code' => 'invalid_number', 'message' => $message, 'permanent' => true];
            }
            if ($code === '130472' || str_contains($haystack, 'opted out') || str_contains($haystack, 'opt-out')) {
                return ['code' => 'opted_out', 'message' => $message, 'permanent' => true];
            }
            if ($code === '368' || str_contains($haystack, 'policy')) {
                return ['code' => 'policy', 'message' => $message, 'permanent' => true];
            }
            if ($status >= 500) {
                return ['code' => 'network', 'message' => $message, 'permanent' => false];
            }

            return ['code' => 'unknown', 'message' => $message, 'permanent' => false];
        }

        return ['code' => 'unknown', 'message' => $e->getMessage(), 'permanent' => false];
    }

    private function contactPreferencesAllow(string $phoneE164, ?string $eventType): bool
    {
        $pref = WhatsAppContactPreference::query()->where('phone_e164', $phoneE164)->first();
        if (! $pref) {
            if ($this->isPromotionalEvent($eventType)) {
                return false;
            }

            return true;
        }

        $messagingAllowed = ($pref->messaging_allowed ?? true) && ($pref->whatsapp_enabled ?? true);
        if (! $messagingAllowed) {
            return false;
        }

        if ($this->isReceiptEvent($eventType) && ! ($pref->receipts_enabled ?? true)) {
            return false;
        }

        if ($this->isReminderEvent($eventType) && ! ($pref->reminders_enabled ?? true)) {
            return false;
        }

        if ($this->isPromotionalEvent($eventType) && ! ($pref->promotional_enabled ?? false)) {
            return false;
        }

        return true;
    }

    private function isReceiptEvent(?string $eventType): bool
    {
        return in_array($eventType, ['payment_confirmed', 'payment_posted'], true);
    }

    private function isReminderEvent(?string $eventType): bool
    {
        return in_array($eventType, ['promise_due_reminder', 'overdue_reminder'], true);
    }

    private function isPromotionalEvent(?string $eventType): bool
    {
        return is_string($eventType) && str_starts_with($eventType, 'promotional_');
    }

    private function hasPermanentFailure(WhatsAppMessage $message): bool
    {
        return WhatsAppFailure::query()
            ->where('message_id', $message->id)
            ->where('is_permanent', true)
            ->exists();
    }

    private function createMessage(string $template, string $language, array $components, ?int $branchId, ?int $customerId, ?int $createdBy, string $status = 'queued', ?string $eventType = null, ?string $phone = null): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'uuid' => (string) Str::uuid(), 'connection_id' => $this->connections->connection()->id,
            'branch_id' => $branchId, 'customer_id' => $customerId, 'created_by' => $createdBy,
            'template_name' => $template, 'language_code' => $language,
            'language' => $language, 'event_type' => $eventType, 'phone_e164' => $phone,
            'payload' => ['components' => $components], 'status' => $status, 'queued_at' => now(),
        ]);
    }

    private function recordPermanentFailure(WhatsAppMessage $message, string $code, string $reason): void
    {
        $message->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_code' => $code,
            'error_message' => $reason,
        ]);
        WhatsAppMessageRecipient::where('message_id', $message->id)->update([
            'status' => 'failed', 'failure_code' => $code, 'failure_reason' => $reason,
        ]);
        WhatsAppFailure::create([
            'message_id' => $message->id,
            'branch_id' => $message->branch_id,
            'code' => $code,
            'message' => $reason,
            'is_permanent' => true,
        ]);
        $this->event($message, 'failed', ['code' => $code, 'reason' => $reason]);
    }

    private function recordRetryFailure(WhatsAppMessage $message, string $code, string $reason): void
    {
        $retryCount = (int) $message->retry_count + 1;
        $message->update([
            'status' => 'retrying',
            'retry_count' => $retryCount,
            'error_code' => $code,
            'error_message' => $reason,
        ]);
        WhatsAppMessageRecipient::where('message_id', $message->id)->update([
            'status' => 'retrying', 'failure_code' => $code, 'failure_reason' => $reason,
        ]);
        WhatsAppFailure::create([
            'message_id' => $message->id,
            'branch_id' => $message->branch_id,
            'code' => $code,
            'message' => $reason,
            'is_permanent' => false,
            'attempts' => $retryCount,
        ]);
        $this->event($message, 'retrying', ['code' => $code, 'reason' => $reason]);
    }

    private function event(WhatsAppMessage $message, string $type, array $payload, ?string $eventId = null): void
    {
        WhatsAppMessageEvent::firstOrCreate(
            $eventId ? ['event_id' => $eventId] : ['message_id' => $message->id, 'type' => $type],
            ['message_id' => $message->id, 'type' => $type, 'payload' => $payload, 'occurred_at' => now()],
        );
    }
}
