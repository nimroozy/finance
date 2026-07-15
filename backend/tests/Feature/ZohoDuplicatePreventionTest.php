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

class ZohoDuplicatePreventionTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_same_zoho_contact_id_does_not_create_duplicates(): void
    {
        $branch = Branch::factory()->create();
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => 'X1',
            'is_active' => true,
        ]);

        $payload = [
            'contacts' => [[
                'contact_id' => 'DUP-1',
                'contact_name' => 'Dup Customer',
                'branch_id' => 'X1',
                'currency_code' => 'AFN',
                'outstanding_receivable_amount' => '1',
                'status' => 'active',
            ]],
            'page_context' => ['has_more_page' => false],
        ];

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response($payload, 200),
        ]);

        $service = app(ZohoCustomerSyncService::class);
        $service->syncFull();
        $service->syncFull();

        $this->assertSame(1, Customer::withoutGlobalScopes()->where('zoho_contact_id', 'DUP-1')->count());
        $this->assertDatabaseCount('customers', 1);
    }
}
