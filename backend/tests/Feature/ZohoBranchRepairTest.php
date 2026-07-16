<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ZohoReportingTagMapping;
use App\Services\Zoho\ZohoBranchMappingService;
use App\Services\Zoho\ZohoBranchReprocessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZohoBranchRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_id_mapping_has_priority(): void
    {
        $branch = Branch::factory()->create(['zoho_location_id' => 'LOC-1']);

        $this->assertSame(
            $branch->id,
            app(ZohoBranchMappingService::class)->resolveBranchId(['location_id' => 'LOC-1'])
        );
    }

    public function test_customer_reprocess_dry_run_does_not_mutate(): void
    {
        $oldBranch = Branch::factory()->create();
        $newBranch = Branch::factory()->create();
        ZohoReportingTagMapping::create([
            'tag_id' => 'TAG-1', 'tag_option_id' => 'OPT-1', 'tag_name' => 'Branch',
            'option_name' => 'New', 'branch_id' => $newBranch->id,
        ]);
        $customer = Customer::factory()->create([
            'branch_id' => $oldBranch->id,
            'reporting_tags' => [['tag_id' => 'TAG-1', 'tag_option_id' => 'OPT-1']],
        ]);

        $stats = app(ZohoBranchReprocessService::class)->customers(false, customerId: $customer->id);

        $this->assertSame(1, $stats['would_change']);
        $this->assertSame($oldBranch->id, $customer->fresh()->branch_id);
        $this->assertDatabaseCount('customer_branch_change_logs', 0);
    }
}
