<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppClient
{
    public function testConnection(?WhatsAppConnection $connection = null): array
    {
        return $this->request($connection)->get($this->url($connection, ''))->throw()->json();
    }

    public function templates(?WhatsAppConnection $connection = null): array
    {
        $businessId = $connection?->business_account_id ?: config('whatsapp.business_account_id');

        return $this->request($connection)
            ->get($this->graphUrl().'/'.$businessId.'/message_templates')
            ->throw()
            ->json();
    }

    public function sendTemplate(
        string $to,
        string $template,
        string $language,
        array $components = [],
        ?WhatsAppConnection $connection = null,
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->request($connection)
            ->post($this->url($connection, '/messages'), $payload)
            ->throw()
            ->json();
    }

    public function raw(string $method, string $url, array $data = [], ?WhatsAppConnection $connection = null): Response
    {
        return $this->request($connection)->send($method, $url, ['json' => $data]);
    }

    private function request(?WhatsAppConnection $connection): PendingRequest
    {
        $token = $connection?->access_token ?: config('whatsapp.access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('WhatsApp access token is not configured.');
        }

        // Deliberately do not log the request or token.
        return Http::acceptJson()->asJson()->withToken($token)->timeout(15);
    }

    private function url(?WhatsAppConnection $connection, string $suffix): string
    {
        $phoneId = $connection?->phone_number_id ?: config('whatsapp.phone_number_id');
        if (! is_string($phoneId) || $phoneId === '') {
            throw new RuntimeException('WhatsApp phone number ID is not configured.');
        }

        return $this->graphUrl().'/'.$phoneId.$suffix;
    }

    private function graphUrl(): string
    {
        return 'https://graph.facebook.com/'.config('whatsapp.api_version', 'v23.0');
    }
}
