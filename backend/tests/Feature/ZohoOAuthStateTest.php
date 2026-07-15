<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZohoOAuthStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_oauth_redirect_stores_hashed_state_and_returns_url(): void
    {
        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/zoho/oauth/redirect');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['authorize_url', 'state']]);

        $state = $response->json('data.state');
        $this->assertNotEmpty($state);
        $this->assertTrue(Cache::has('zoho:oauth:state:'.hash('sha256', $state)));
        $this->assertStringContainsString('accounts.zoho.com/oauth/v2/auth', $response->json('data.authorize_url'));
    }

    public function test_oauth_callback_rejects_invalid_state(): void
    {
        Http::fake();

        $response = $this->get('/api/v1/zoho/oauth/callback?code=abc&state=invalid');

        $response->assertRedirect();
        $this->assertStringContainsString('connected=0', $response->headers->get('Location'));
    }

    public function test_oauth_callback_exchanges_code_with_valid_state(): void
    {
        Http::fake([
            'https://accounts.zoho.com/oauth/v2/token' => Http::response([
                'access_token' => 'access-from-code',
                'refresh_token' => 'refresh-from-code',
                'expires_in' => 3600,
                'api_domain' => 'https://www.zohoapis.com',
                'scope' => 'ZohoBooks.fullaccess.all',
            ], 200),
        ]);

        $state = 'valid-oauth-state-token-1234567890';
        Cache::put('zoho:oauth:state:'.hash('sha256', $state), [
            'data_center' => 'us',
            'accounts_domain' => 'https://accounts.zoho.com',
            'api_domain' => 'https://www.zohoapis.com',
        ], now()->addMinutes(10));

        $response = $this->get('/api/v1/zoho/oauth/callback?code=auth-code&state='.$state);

        $response->assertRedirect();
        $this->assertStringContainsString('connected=1', $response->headers->get('Location'));
        $this->assertDatabaseCount('zoho_connections', 1);
        $this->assertFalse(Cache::has('zoho:oauth:state:'.hash('sha256', $state)));
    }
}
