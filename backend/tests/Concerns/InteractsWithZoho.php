<?php

namespace Tests\Concerns;

use App\Models\ZohoConnection;
use Illuminate\Support\Facades\Http;

trait InteractsWithZoho
{
    protected function createConnectedZoho(?array $overrides = []): ZohoConnection
    {
        return ZohoConnection::query()->create(array_merge([
            'organization_id' => config('zoho.organization_id', 'org-test-1'),
            'organization_name' => 'Test Org',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
            'data_center' => 'us',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => 'ZohoBooks.fullaccess.all',
            'status' => ZohoConnection::STATUS_CONNECTED,
            'last_connected_at' => now(),
        ], $overrides ?? []));
    }

    protected function fakeZohoApi(array $responses = []): void
    {
        Http::fake(array_merge([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'api_domain' => 'https://www.zohoapis.com',
                'scope' => 'ZohoBooks.fullaccess.all',
            ], 200),
            'https://www.zohoapis.com/books/v3/*' => Http::response([
                'code' => 0,
                'message' => 'success',
            ], 200),
        ], $responses));
    }
}
