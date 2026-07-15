<?php

namespace Tests\Feature;

use App\Services\Zoho\ZohoTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoTokenRefreshTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_get_valid_access_token_refreshes_when_expiring_within_two_minutes(): void
    {
        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'refreshed-access',
                'expires_in' => 3600,
                'api_domain' => 'https://www.zohoapis.com',
            ], 200),
        ]);

        $connection = $this->createConnectedZoho([
            'access_token' => 'old-access',
            'refresh_token' => 'keep-refresh',
            'token_expires_at' => now()->addMinute(),
        ]);

        $token = app(ZohoTokenService::class)->getValidAccessToken($connection);

        $this->assertSame('refreshed-access', $token);
        $this->assertSame('refreshed-access', $connection->fresh()->access_token);
        $this->assertSame('keep-refresh', $connection->fresh()->refresh_token);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://accounts.zoho.com/oauth/v2/token'
                && $request['grant_type'] === 'refresh_token';
        });
    }

    public function test_does_not_refresh_when_token_still_valid(): void
    {
        Http::fake();

        $connection = $this->createConnectedZoho([
            'access_token' => 'still-valid',
            'token_expires_at' => now()->addMinutes(30),
        ]);

        $token = app(ZohoTokenService::class)->getValidAccessToken($connection);

        $this->assertSame('still-valid', $token);
        Http::assertNothingSent();
    }
}
