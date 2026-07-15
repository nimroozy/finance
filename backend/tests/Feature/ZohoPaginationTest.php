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

class ZohoPaginationTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_sync_follows_pagination_across_pages(): void
    {
        $branch = Branch::factory()->create();
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => 'P1',
            'is_active' => true,
        ]);

        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::sequence()
                ->push([
                    'contacts' => [[
                        'contact_id' => 'PAGE-1',
                        'contact_name' => 'Page One',
                        'branch_id' => 'P1',
                        'currency_code' => 'AFN',
                        'status' => 'active',
                    ]],
                    'page_context' => ['has_more_page' => true],
                ], 200)
                ->push([
                    'contacts' => [[
                        'contact_id' => 'PAGE-2',
                        'contact_name' => 'Page Two',
                        'branch_id' => 'P1',
                        'currency_code' => 'AFN',
                        'status' => 'active',
                    ]],
                    'page_context' => ['has_more_page' => false],
                ], 200),
        ]);

        $stats = app(ZohoCustomerSyncService::class)->syncFull();

        $this->assertSame(2, $stats['processed']);
        $this->assertSame(2, $stats['created']);
        $this->assertSame(2, Customer::withoutGlobalScopes()->count());
    }
}
