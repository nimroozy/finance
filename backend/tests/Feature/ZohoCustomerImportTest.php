<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ZohoBranchMapping;
use App\Models\Branch;
use App\Services\Zoho\ZohoCustomerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoCustomerImportTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_imports_customers_and_stores_zoho_contact_ids(): void
    {
        $branch = Branch::factory()->create(['code' => 'KBL']);
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => '1001',
            'is_active' => true,
        ]);

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response([
                'contacts' => [
                    [
                        'contact_id' => 'C-100',
                        'contact_name' => 'Ahmad Customer',
                        'contact_number' => 'CUST-100',
                        'email' => 'ahmad@example.com',
                        'currency_code' => 'AFN',
                        'outstanding_receivable_amount' => '1500.5000',
                        'branch_id' => '1001',
                        'status' => 'active',
                        'created_time' => '2026-01-01T10:00:00+00:00',
                        'last_modified_time' => '2026-01-02T10:00:00+00:00',
                    ],
                ],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $stats = app(ZohoCustomerSyncService::class)->syncFull();

        $this->assertSame(1, $stats['created']);
        $this->assertDatabaseHas('customers', [
            'zoho_contact_id' => 'C-100',
            'contact_name' => 'Ahmad Customer',
            'branch_id' => $branch->id,
            'is_unmapped' => 0,
            'sync_status' => 'synced',
        ]);

        $customer = Customer::withoutGlobalScopes()->where('zoho_contact_id', 'C-100')->first();
        $this->assertNotNull($customer);
        $this->assertSame('1500.5000', (string) $customer->outstanding_receivable);
        $this->assertDatabaseHas('zoho_entity_mappings', [
            'entity_type' => 'customer',
            'zoho_id' => 'C-100',
            'local_id' => $customer->id,
        ]);
    }
}
