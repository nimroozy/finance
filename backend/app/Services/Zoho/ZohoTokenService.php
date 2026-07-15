<?php

namespace App\Services\Zoho;

use App\Models\ZohoConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZohoTokenService
{
    public function __construct(
        protected ZohoConfig $config,
        protected ZohoApiLogger $logger,
    ) {}

    /**
     * Persist tokens encrypted at rest via model casts.
     *
     * @param  array{
     *     access_token:string,
     *     refresh_token?:string|null,
     *     expires_in?:int,
     *     api_domain?:string|null,
     *     accounts_domain?:string|null,
     *     data_center?:string|null,
     *     scopes?:string|null,
     *     connected_by?:int|null,
     *     organization_id?:string|null,
     *     organization_name?:string|null
     * }  $payload
     */
    public function storeEncrypted(array $payload, ?ZohoConnection $connection = null): ZohoConnection
    {
        $connection ??= ZohoConnection::current() ?? new ZohoConnection;

        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        $connection->fill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => now()->addSeconds($expiresIn),
            'accounts_domain' => $payload['accounts_domain']
                ?? $connection->accounts_domain
                ?? $this->config->accountsDomain(),
            'api_domain' => $payload['api_domain']
                ?? $connection->api_domain
                ?? $this->config->apiDomain(),
            'data_center' => $payload['data_center']
                ?? $connection->data_center
                ?? config('zoho.default_data_center', 'us'),
            'scopes' => $payload['scopes'] ?? $connection->scopes,
            'connected_by' => $payload['connected_by'] ?? $connection->connected_by,
            'organization_id' => $payload['organization_id'] ?? $connection->organization_id,
            'organization_name' => $payload['organization_name'] ?? $connection->organization_name,
            'status' => ZohoConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        $connection->save();

        return $connection->fresh();
    }

    public function getValidAccessToken(?ZohoConnection $connection = null): string
    {
        $connection ??= ZohoConnection::current();

        if (! $connection || ! $connection->refresh_token) {
            throw new RuntimeException('Zoho is not connected.');
        }

        if ($connection->tokenNeedsRefresh()) {
            $connection = $this->refresh($connection);
        }

        $token = $connection->access_token;

        if (! filled($token)) {
            throw new RuntimeException('Zoho access token is unavailable.');
        }

        return $token;
    }

    public function refresh(ZohoConnection $connection): ZohoConnection
    {
        $this->config->assertCredentialsConfigured();

        if (! filled($connection->refresh_token)) {
            throw new RuntimeException('Zoho refresh token is missing.');
        }

        $accountsDomain = rtrim($connection->accounts_domain ?: $this->config->accountsDomain(), '/');
        $started = microtime(true);

        $response = Http::asForm()->post($accountsDomain.'/oauth/v2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->config->clientId(),
            'client_secret' => $this->config->clientSecret(),
            'refresh_token' => $connection->refresh_token,
        ]);

        $duration = (int) ((microtime(true) - $started) * 1000);

        $this->logger->log([
            'request_type' => 'oauth',
            'endpoint_name' => 'oauth/v2/token/refresh',
            'method' => 'POST',
            'http_status' => $response->status(),
            'attempt' => 1,
            'duration_ms' => $duration,
            'success' => $response->successful(),
            'error_message' => $response->successful() ? null : $response->body(),
        ]);

        if (! $response->successful()) {
            $connection->update([
                'status' => ZohoConnection::STATUS_ERROR,
                'last_error' => $this->logger->sanitize($response->body()),
            ]);

            throw new RuntimeException('Zoho token refresh failed.');
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new RuntimeException('Zoho refresh response missing access_token.');
        }

        return $this->storeEncrypted([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
            'api_domain' => $data['api_domain'] ?? $connection->api_domain,
            'accounts_domain' => $accountsDomain,
            'data_center' => $connection->data_center,
            'scopes' => $data['scope'] ?? $connection->scopes,
        ], $connection);
    }
}
