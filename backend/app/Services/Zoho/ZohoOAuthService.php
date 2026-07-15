<?php

namespace App\Services\Zoho;

use App\Models\User;
use App\Models\ZohoConnection;
use App\Models\ZohoOrganization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ZohoOAuthService
{
    public function __construct(
        protected ZohoConfig $config,
        protected ZohoTokenService $tokenService,
        protected ZohoApiLogger $logger,
    ) {}

    /**
     * @return array{url:string,state:string}
     */
    public function authorizeUrl(?string $dataCenter = null): array
    {
        $this->config->assertCredentialsConfigured();

        $dc = $this->config->resolveDataCenter($dataCenter);
        $state = Str::random(40);
        $hashed = hash('sha256', $state);

        Cache::put($this->stateCacheKey($hashed), [
            'data_center' => $dc['code'],
            'accounts_domain' => $dc['accounts'],
            'api_domain' => $dc['api'],
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        $query = http_build_query([
            'scope' => $this->config->oauthScopes(),
            'client_id' => $this->config->clientId(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $this->config->redirectUri(),
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return [
            'url' => rtrim($dc['accounts'], '/').'/oauth/v2/auth?'.$query,
            'state' => $state,
        ];
    }

    public function validateState(string $state): array
    {
        $hashed = hash('sha256', $state);
        $payload = Cache::pull($this->stateCacheKey($hashed));

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid or expired OAuth state.');
        }

        return $payload;
    }

    /**
     * Exchange authorization code and persist encrypted tokens.
     */
    public function exchangeCode(string $code, array $statePayload, ?User $user = null): ZohoConnection
    {
        $this->config->assertCredentialsConfigured();

        $accountsDomain = rtrim($statePayload['accounts_domain'] ?? $this->config->accountsDomain(), '/');
        $started = microtime(true);

        $response = Http::asForm()->post($accountsDomain.'/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config->clientId(),
            'client_secret' => $this->config->clientSecret(),
            'redirect_uri' => $this->config->redirectUri(),
            'code' => $code,
        ]);

        $duration = (int) ((microtime(true) - $started) * 1000);

        $this->logger->log([
            'request_type' => 'oauth',
            'endpoint_name' => 'oauth/v2/token',
            'method' => 'POST',
            'http_status' => $response->status(),
            'attempt' => 1,
            'duration_ms' => $duration,
            'success' => $response->successful(),
            'error_message' => $response->successful() ? null : $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Zoho token exchange failed: '.$this->logger->sanitize($response->body()));
        }

        $data = $response->json();

        if (empty($data['access_token']) || empty($data['refresh_token'])) {
            throw new RuntimeException('Zoho token response missing access_token or refresh_token.');
        }

        return $this->tokenService->storeEncrypted([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
            'api_domain' => $data['api_domain'] ?? ($statePayload['api_domain'] ?? null),
            'accounts_domain' => $accountsDomain,
            'data_center' => $statePayload['data_center'] ?? 'us',
            'scopes' => $data['scope'] ?? $this->config->oauthScopes(),
            'connected_by' => $user?->id,
        ]);
    }

    public function refreshToken(ZohoConnection $connection): ZohoConnection
    {
        return $this->tokenService->refresh($connection);
    }

    public function disconnect(ZohoConnection $connection): void
    {
        $connection->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => ZohoConnection::STATUS_DISCONNECTED,
            'last_error' => null,
        ]);

        ZohoOrganization::query()
            ->where('zoho_connection_id', $connection->id)
            ->update(['is_selected' => false]);
    }

    protected function stateCacheKey(string $hashedState): string
    {
        return 'zoho:oauth:state:'.$hashedState;
    }
}
