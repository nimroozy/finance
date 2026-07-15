<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ZohoBranchMapping;
use App\Models\ZohoReportingTagMapping;
use App\Services\Zoho\ZohoBranchMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZohoBranchMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_branch_from_zoho_branch_mapping(): void
    {
        $branch = Branch::factory()->create();
        ZohoBranchMapping::query()->create([
            'branch_id' => $branch->id,
            'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_BRANCH,
            'zoho_value' => '55',
            'is_active' => true,
        ]);

        $resolved = app(ZohoBranchMappingService::class)->resolveBranchId([
            'branch_id' => '55',
        ]);

        $this->assertSame($branch->id, $resolved);
    }

    public function test_resolves_branch_from_reporting_tag_mapping_table(): void
    {
        $branch = Branch::factory()->create();
        ZohoReportingTagMapping::query()->create([
            'tag_id' => 'TAG-1',
            'tag_option_id' => 'OPT-9',
            'tag_name' => 'Branch',
            'option_name' => 'Kabul',
            'branch_id' => $branch->id,
        ]);

        $resolved = app(ZohoBranchMappingService::class)->resolveBranchId([
            'reporting_tags' => [[
                'tag_id' => 'TAG-1',
                'tag_option_id' => 'OPT-9',
            ]],
        ]);

        $this->assertSame($branch->id, $resolved);
    }

    public function test_returns_null_when_unmapped(): void
    {
        $resolved = app(ZohoBranchMappingService::class)->resolveBranchId([
            'branch_id' => 'unknown',
        ]);

        $this->assertNull($resolved);
    }
}
