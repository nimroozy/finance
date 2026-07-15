<?php

namespace Tests\Feature;

use App\Services\Zoho\ZohoApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoRateLimitRetryTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_retries_on_429_using_retry_after(): void
    {
        config(['zoho.http.retries' => 3]);

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::sequence()
                ->push(['message' => 'rate limited'], 429, ['Retry-After' => '0'])
                ->push([
                    'contacts' => [],
                    'page_context' => ['has_more_page' => false],
                    'code' => 0,
                ], 200),
        ]);

        $result = app(ZohoApiClient::class)->get('contacts');

        $this->assertIsArray($result);
        Http::assertSentCount(2);
    }
}
