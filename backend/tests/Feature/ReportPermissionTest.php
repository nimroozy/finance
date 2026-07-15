<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class ReportPermissionTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_auditor_can_view_reports(): void
    {
        $this->seedRoles();
        $auditor = User::factory()->create();
        $auditor->assignRole(User::ROLE_AUDITOR);
        $this->actingAsUser($auditor);

        $this->getJson('/api/v1/reports/assignments-by-collector')->assertOk();
        $this->getJson('/api/v1/reports/visits-by-outcome')->assertOk();
        $this->getJson('/api/v1/reports/overdue-promises')->assertOk();
    }

    public function test_collector_cannot_view_reports(): void
    {
        $branch = $this->makeBranch();
        [$collectorUser] = $this->makeCollectorUser($branch);
        $this->actingAsUser($collectorUser);

        $this->getJson('/api/v1/reports/assignments-by-collector')->assertStatus(403);
        $this->getJson('/api/v1/reports/visits-by-outcome')->assertStatus(403);
    }

    public function test_branch_manager_can_export_csv(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);

        $this->get('/api/v1/reports/unassigned-debtors?export=csv')
            ->assertOk()
            ->assertHeaderContains('content-type', 'text/csv');
    }
}
