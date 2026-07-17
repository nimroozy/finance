<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppConnection;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\WhatsApp\WhatsAppTemplate;
use App\Models\WhatsApp\WhatsAppWebhookEvent;
use Throwable;

class WhatsAppConnectionService
{
    public function __construct(private WhatsAppClient $client) {}

    public function connection(): WhatsAppConnection
    {
        return WhatsAppConnection::query()->firstOrCreate(
            ['name' => 'default'],
            [
                'phone_number_id' => config('whatsapp.phone_number_id'),
                'business_account_id' => config('whatsapp.business_account_id'),
                'access_token' => config('whatsapp.access_token'),
                'status' => config('whatsapp.access_token') ? 'configured' : 'disconnected',
                'is_paused' => config('whatsapp.outgoing_paused', false),
            ],
        );
    }

    public function status(): array
    {
        $connection = $this->connection();

        return [
            'connected' => in_array($connection->status, ['configured', 'connected'], true),
            'status' => $connection->status,
            'paused' => $connection->is_paused,
            'phone_number_id' => $connection->phone_number_id,
            'business_account_id' => $connection->business_account_id,
            'api_version' => config('whatsapp.api_version', 'v23.0'),
            'template_count' => WhatsAppTemplate::query()
                ->where('connection_id', $connection->id)
                ->count(),
            'pending_messages' => WhatsAppMessage::query()
                ->whereIn('status', ['queued', 'retrying'])
                ->count(),
            'failed_messages' => WhatsAppMessage::query()
                ->where('status', 'failed')
                ->count(),
            'last_successful_api_call' => $connection->last_successful_api_call ?? $connection->last_tested_at,
            'last_webhook_received' => $connection->last_webhook_received
                ?? WhatsAppWebhookEvent::query()->max('created_at'),
            'token_masked' => $connection->access_token ? '••••••••'.substr((string) $connection->access_token, -4) : null,
            'last_tested_at' => $connection->last_tested_at,
            'last_error' => $connection->last_error,
        ];
    }

    public function test(): array
    {
        $connection = $this->connection();
        try {
            $result = $this->client->testConnection($connection);
            $connection->update([
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_successful_api_call' => now(),
                'last_error' => null,
            ]);

            return ['ok' => true, 'response' => $result];
        } catch (Throwable $e) {
            $connection->update(['status' => 'error', 'last_tested_at' => now(), 'last_error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function pause(): WhatsAppConnection
    {
        $connection = $this->connection();
        $connection->update(['is_paused' => true]);

        return $connection->fresh();
    }

    public function resume(): WhatsAppConnection
    {
        $connection = $this->connection();
        $connection->update(['is_paused' => false]);

        return $connection->fresh();
    }
}
