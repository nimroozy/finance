<?php

namespace Tests\Feature;

use App\Services\Zoho\ZohoOrganizationStructureSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoOrganizationStructureSyncTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_upserts_structure_and_synthetic_tag_options_idempotently(): void
    {
        $this->createConnectedZoho();
        Http::fake([
            '*books/v3/locations*' => Http::response(['locations' => [['location_id' => 'L1', 'name' => 'Kabul']]]),
            '*books/v3/settings/tags*' => Http::response(['tags' => [[
                'tag_id' => 'T1', 'name' => 'Branch', 'tag_options' => 'Kabul, Herat',
            ]]]),
            '*books/v3/settings/paymentmodes*' => Http::response(['payment_modes' => [[
                'payment_mode_id' => 'PM1', 'name' => 'Bank',
            ]]]),
            '*books/v3/chartofaccounts*' => Http::response(['accounts' => [[
                'account_id' => 'A1', 'name' => 'Receivable', 'account_type' => 'accounts_receivable',
            ]], 'page_context' => ['has_more_page' => false]]),
        ]);

        $service = app(ZohoOrganizationStructureSyncService::class);
        $service->sync();
        $service->sync();

        $this->assertDatabaseCount('zoho_locations', 1);
        $this->assertDatabaseCount('zoho_reporting_tags', 1);
        $this->assertDatabaseCount('zoho_reporting_tag_options', 2);
        $this->assertDatabaseCount('zoho_payment_modes', 1);
        $this->assertDatabaseCount('zoho_accounts', 1);
        $this->assertDatabaseHas('zoho_reporting_tag_options', ['zoho_tag_id' => 'T1', 'name' => 'Kabul']);
    }
}
