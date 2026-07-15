<?php

namespace Tests\Feature;

use App\Services\Zoho\ZohoApiLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZohoApiLogSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizes_authorization_and_tokens_from_error_messages(): void
    {
        $logger = app(ZohoApiLogger::class);

        $raw = 'Authorization: Bearer secret-token-value access_token=abc123 refresh_token=xyz789 client_secret=shh';
        $sanitized = $logger->sanitize($raw);

        $this->assertStringNotContainsString('secret-token-value', $sanitized);
        $this->assertStringNotContainsString('abc123', $sanitized);
        $this->assertStringNotContainsString('xyz789', $sanitized);
        $this->assertStringNotContainsString('shh', $sanitized);
        $this->assertStringContainsString('[REDACTED]', $sanitized);

        $log = $logger->log([
            'request_type' => 'api',
            'endpoint_name' => 'contacts',
            'method' => 'GET',
            'success' => false,
            'error_message' => 'Zoho-oauthtoken Bearer leaked-token-here failed',
            'attempt' => 1,
        ]);

        $this->assertStringNotContainsString('leaked-token-here', (string) $log->error_message);
    }
}
