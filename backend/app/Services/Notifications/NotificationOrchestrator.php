<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\WhatsApp\WhatsAppNotificationRule;
use App\Services\WhatsApp\WhatsAppMessageService;

class NotificationOrchestrator
{
    public function __construct(
        private NotificationService $inApp,
        private WhatsAppMessageService $whatsApp,
    ) {}

    /**
     * Creates one notification intent and routes it through configured channels.
     * Domain services only call this class; they never call Meta directly.
     */
    public function createIntent(string $eventType, array $payload, array $channels = ['in_app', 'whatsapp']): array
    {
        $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : null;
        $rule = WhatsAppNotificationRule::query()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->orderByDesc('branch_id')
            ->first();
        $created = [];

        if (in_array('in_app', $channels, true) && ($rule?->in_app_enabled ?? true) && isset($payload['user_id'])) {
            $user = User::find($payload['user_id']);
            if ($user) {
                $created['in_app'] = $this->inApp->notify(
                    $user,
                    $eventType,
                    (string) ($payload['title'] ?? $eventType),
                    $payload['body'] ?? null,
                    $payload,
                    $branchId,
                );
            }
        }

        if (
            in_array('whatsapp', $channels, true)
            && ($rule?->whatsapp_enabled ?? false)
            && ! empty($payload['phone'])
            && ($rule?->template_name || ! empty($payload['template_name']))
        ) {
            $created['whatsapp'] = $this->whatsApp->queueTemplate(
                $payload['phone'],
                $rule?->template_name ?? $payload['template_name'],
                $rule?->language_code ?? ($payload['language_code'] ?? 'fa'),
                $payload['components'] ?? [],
                $branchId,
                $payload['customer_id'] ?? null,
                $payload['user_id'] ?? null,
                $eventType,
            );
        }

        return $created;
    }
}
