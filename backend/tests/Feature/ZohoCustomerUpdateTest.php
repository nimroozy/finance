<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ZohoBranchMapping;
use App\Services\Zoho\ZohoCustomerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoCustomerUpdateTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_updates_existing_customer_by_zoho_contact_id(): void
    {
        $branch = Branch::factory()->create();
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => '2002',
            'is_active' => true,
        ]);

        $existing = Customer::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'zoho_contact_id' => 'C-200',
            'contact_name' => 'Old Name',
            'outstanding_receivable' => '10.0000',
            'currency' => 'AFN',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_unmapped' => false,
        ]);

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response([
                'contacts' => [[
                    'contact_id' => 'C-200',
                    'contact_name' => 'Updated Name',
                    'outstanding_receivable_amount' => '99.2500',
                    'branch_id' => '2002',
                    'currency_code' => 'AFN',
                    'status' => 'active',
                    'last_modified_time' => now()->toIso8601String(),
                ]],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $stats = app(ZohoCustomerSyncService::class)->syncFull();

        $this->assertSame(1, $stats['updated']);
        $this->assertSame(0, $stats['created']);
        $this->assertDatabaseCount('customers', 1);

        $existing->refresh();
        $this->assertSame('Updated Name', $existing->contact_name);
        $this->assertSame('99.2500', (string) $existing->outstanding_receivable);
    }
}
