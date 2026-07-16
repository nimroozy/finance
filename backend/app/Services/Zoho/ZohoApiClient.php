<?php

namespace App\Services\Zoho;

use App\Models\ZohoConnection;
use App\Models\ZohoSyncJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZohoApiClient
{
    protected ?ZohoSyncJob $syncJob = null;

    public function __construct(
        protected ZohoConfig $config,
        protected ZohoTokenService $tokenService,
        protected ZohoApiLogger $logger,
    ) {}

    public function forSyncJob(?ZohoSyncJob $job): self
    {
        $clone = clone $this;
        $clone->syncJob = $job;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $endpoint, array $query = [], ?string $entityType = null): array
    {
        return $this->request('GET', $endpoint, ['query' => $query], $entityType);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $endpoint, array $payload = [], ?string $entityType = null): array
    {
        return $this->request('POST', $endpoint, ['json' => $payload], $entityType);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $endpoint, array $query = [], ?string $entityType = null): array
    {
        return $this->request('DELETE', $endpoint, ['query' => $query], $entityType);
    }

    /**
     * @param  array{query?:array<string,mixed>,json?:array<string,mixed>}  $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, array $options = [], ?string $entityType = null): array
    {
        $connection = ZohoConnection::current();

        if (! $connection) {
            throw new RuntimeException('Zoho is not connected.');
        }

        $token = $this->tokenService->getValidAccessToken($connection);
        $path = ltrim($endpoint, '/');
        $organizationId = $connection->organization_id ?: $this->config->organizationId();

        $requiresOrg = ! str_starts_with($path, 'organizations');

        if ($requiresOrg && ! $organizationId) {
            throw new RuntimeException('Zoho organization_id is not selected.');
        }

        $base = rtrim($connection->api_domain ?: $this->config->apiDomain(), '/');
        $url = "{$base}/books/v3/{$path}";

        $query = $options['query'] ?? [];
        if ($organizationId) {
            $query['organization_id'] = $organizationId;
        }

        $maxAttempts = max(1, (int) config('zoho.http.retries', 3));
        $attempt = 0;
        $lastException = null;
        $refreshedAfterUnauthorized = false;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $started = microtime(true);

            try {
                $pending = Http::withHeaders([
                    'Authorization' => 'Zoho-oauthtoken '.$token,
                ])
                    ->acceptJson()
                    ->timeout((int) config('zoho.http.timeout', 30));

                $urlWithQuery = $query === [] ? $url : $url.'?'.http_build_query($query);

                /** @var Response $response */
                $response = match (strtoupper($method)) {
                    'GET' => $pending->get($url, $query),
                    'POST' => $pending->asJson()->post($urlWithQuery, $options['json'] ?? []),
                    'PUT' => $pending->asJson()->put($urlWithQuery, $options['json'] ?? []),
                    'DELETE' => $pending->delete($urlWithQuery),
                    default => throw new RuntimeException("Unsupported HTTP method [{$method}]."),
                };

                $duration = (int) ((microtime(true) - $started) * 1000);
                $json = $response->json() ?? [];

                $this->logger->log([
                    'zoho_sync_job_id' => $this->syncJob?->id,
                    'request_type' => 'api',
                    'endpoint_name' => $path,
                    'method' => strtoupper($method),
                    'entity_type' => $entityType,
                    'http_status' => $response->status(),
                    'zoho_code' => isset($json['code']) ? (string) $json['code'] : null,
                    'attempt' => $attempt,
                    'duration_ms' => $duration,
                    'success' => $response->successful(),
                    'error_message' => $response->successful() ? null : $response->body(),
                ]);

                if ($response->status() === 429) {
                    if ($attempt >= $maxAttempts) {
                        throw $this->apiException($response, $path);
                    }
                    $retryAfter = (int) ($response->header('Retry-After') ?: 1);
                    usleep(max(1, $retryAfter) * 1_000_000);

                    continue;
                }

                if ($response->serverError() && $attempt < $maxAttempts) {
                    usleep($this->backoffMicros($attempt));

                    continue;
                }

                if ($response->status() === 401 && ! $refreshedAfterUnauthorized) {
                    $connection = $this->tokenService->refresh($connection);
                    $token = $connection->access_token;
                    $refreshedAfterUnauthorized = true;
                    $attempt--;

                    continue;
                }

                if (! $response->successful()) {
                    throw $this->apiException($response, $path);
                }

                return is_array($json) ? $json : [];
            } catch (ZohoApiException $e) {
                throw $e;
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts) {
                    throw new ZohoApiException(
                        'Zoho network error: '.$e->getMessage(),
                        endpoint: $path,
                        errorClass: 'network',
                        previous: $e,
                    );
                }

                usleep($this->backoffMicros($attempt));
            }
        }

        throw new ZohoApiException(
            $lastException?->getMessage() ?? 'Zoho API request failed.',
            endpoint: $path,
            errorClass: 'network',
            previous: $lastException,
        );
    }

    protected function backoffMicros(int $attempt): int
    {
        $base = (int) config('zoho.http.retry_delay_ms', 500);

        return (int) ($base * (2 ** ($attempt - 1)) * 1000);
    }

    protected function apiException(Response $response, string $endpoint): ZohoApiException
    {
        $body = $response->json() ?? $response->body();
        $message = is_array($body)
            ? (string) ($body['message'] ?? $body['error'] ?? $response->body())
            : (string) $body;
        $errorClass = ZohoErrorClassifier::classify($message, $response->status());

        return new ZohoApiException(
            'Zoho API error ['.$response->status().']: '.$this->logger->sanitize($message),
            $response->status(),
            is_array($body) && isset($body['code']) ? (string) $body['code'] : null,
            $endpoint,
            $body,
            $errorClass,
        );
    }
}
