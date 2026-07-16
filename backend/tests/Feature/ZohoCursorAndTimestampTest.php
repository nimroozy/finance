<?php

namespace Tests\Feature;

use App\Models\ZohoSyncCursor;
use App\Services\Zoho\ZohoApiException;
use App\Services\Zoho\ZohoCustomerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoCursorAndTimestampTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_customer_sync_uses_offset_without_colon(): void
    {
        $this->createConnectedZoho();
        ZohoSyncCursor::create(['entity' => 'customers', 'successful_cursor' => '2026-07-16 10:00:00']);
        Http::fake(['*contacts*' => Http::response(['contacts' => [], 'page_context' => ['has_more_page' => false]])]);

        app(ZohoCustomerSyncService::class)->syncIncremental();

        Http::assertSent(function ($request) {
            $url = urldecode($request->url());

            return str_contains($url, '+0000') && ! str_contains($url, '+00:00');
        });
    }

    public function test_cursor_is_not_advanced_when_pagination_fails(): void
    {
        $this->createConnectedZoho();
        $old = now()->subHour()->startOfSecond();
        ZohoSyncCursor::create(['entity' => 'customers', 'successful_cursor' => $old]);
        Http::fake([
            '*contacts*' => Http::sequence()
                ->push([
                    'contacts' => [['contact_id' => 'C-PAGE-1', 'contact_name' => 'Page One']],
                    'page_context' => ['has_more_page' => true],
                ], 200)
                ->push(['message' => 'invalid last_modified_time'], 400),
        ]);

        try {
            app(ZohoCustomerSyncService::class)->syncIncremental();
            $this->fail('Expected ZohoApiException.');
        } catch (ZohoApiException) {
            //
        }

        $this->assertTrue(ZohoSyncCursor::where('entity', 'customers')->first()->successful_cursor->equalTo($old));
    }
}
