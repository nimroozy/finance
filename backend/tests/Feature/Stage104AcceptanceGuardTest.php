<?php

namespace Tests\Feature;

use App\Support\Acceptance\AcceptanceDbAssertions;
use Database\Seeders\AcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage104AcceptanceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_ping_available_in_testing(): void
    {
        $res = $this->getJson('/api/v1/acceptance/ping');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.acceptance', true)
            ->assertJsonPath('data.radius_enabled', false);
    }

    public function test_acceptance_assert_customer_fixture(): void
    {
        $this->seed(AcceptanceSeeder::class);

        $res = $this->postJson('/api/v1/acceptance/assert', [
            'entity' => 'customer',
            'key' => 'zoho_contact_id',
            'value' => AcceptanceSeeder::ZOHO_CONTACT_ID,
            'expect' => ['status' => 'active'],
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/acceptance/assert', [
            'entity' => 'user',
            'key' => 'username',
            'value' => 'ACCEPTANCE-collector',
            'expect' => ['status' => 'active'],
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_stock_reconciliation_endpoint(): void
    {
        $res = $this->getJson('/api/v1/acceptance/stock-reconciliation');
        $res->assertOk()->assertJsonPath('success', true);
    }

    public function test_acceptance_seeder_refuses_production_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV=production');

        (new AcceptanceSeeder)->run();
    }

    public function test_acceptance_assert_command_refuses_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('acceptance:assert', ['--stock' => true])
            ->assertFailed();
    }

    public function test_db_assertions_helper_stock_on_empty(): void
    {
        $result = app(AcceptanceDbAssertions::class)->stockReconciliation();
        $this->assertTrue($result['ok']);
        $this->assertSame('0.000', $result['closing']);
    }
}
