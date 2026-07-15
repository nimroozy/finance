<?php

namespace Tests\Feature;

use App\Models\ZohoConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoTokenEncryptionTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $connection = $this->createConnectedZoho([
            'access_token' => 'plain-access-token',
            'refresh_token' => 'plain-refresh-token',
        ]);

        $row = DB::table('zoho_connections')->where('id', $connection->id)->first();

        $this->assertNotSame('plain-access-token', $row->access_token);
        $this->assertNotSame('plain-refresh-token', $row->refresh_token);
        $this->assertSame('plain-access-token', Crypt::decryptString($row->access_token));
        $this->assertSame('plain-refresh-token', Crypt::decryptString($row->refresh_token));

        $fresh = ZohoConnection::query()->findOrFail($connection->id);
        $this->assertSame('plain-access-token', $fresh->access_token);
        $this->assertSame('plain-refresh-token', $fresh->refresh_token);
    }

    public function test_status_endpoint_masks_tokens(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\App\Models\User::ROLE_SUPER_ADMIN);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->createConnectedZoho();

        $this->getJson('/api/v1/zoho/status')
            ->assertOk()
            ->assertJsonPath('data.has_access_token', true)
            ->assertJsonPath('data.has_refresh_token', true)
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.refresh_token');
    }
}
