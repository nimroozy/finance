<?php

namespace Tests\Feature;

use App\Models\Org\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage71PickersTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pickers_respect_branch_isolation(): void
    {
        $branchA = $this->makeBranch(['code' => 'PKA']);
        $branchB = $this->makeBranch(['code' => 'PKB']);
        $this->seed(Stage7OrgSeeder::class);

        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        $customerA = $this->makeCustomer($branchA, ['contact_name' => 'Customer A', 'customer_number' => 'CA-1']);
        $customerB = $this->makeCustomer($branchB, ['contact_name' => 'Customer B', 'customer_number' => 'CB-1']);

        $this->actingAsUser($managerA);

        $customers = $this->getJson('/api/v1/pickers/customers?q=Customer')->assertOk()->json('data');
        $ids = collect($customers)->pluck('id')->all();
        $this->assertContains($customerA->id, $ids);
        $this->assertNotContains($customerB->id, $ids);

        $branches = $this->getJson('/api/v1/pickers/branches')->assertOk()->json('data');
        $branchIds = collect($branches)->pluck('id')->all();
        $this->assertContains($branchA->id, $branchIds);
        $this->assertNotContains($branchB->id, $branchIds);

        $users = $this->getJson('/api/v1/pickers/users?q='.urlencode($managerB->email))->assertOk()->json('data');
        $this->assertSame([], $users);

        $deptA = Department::query()->where('branch_id', $branchA->id)->where('code', 'technical')->firstOrFail();
        $depts = $this->getJson('/api/v1/pickers/departments?branch_id='.$branchA->id)->assertOk()->json('data');
        $this->assertTrue(collect($depts)->contains(fn ($d) => (int) $d['id'] === $deptA->id));

        $teams = $this->getJson('/api/v1/pickers/teams?department_id='.$deptA->id)->assertOk()->json('data');
        $this->assertNotEmpty($teams);
    }

    public function test_user_picker_allows_tickets_assign_without_users_view(): void
    {
        $branch = $this->makeBranch(['code' => 'PKU']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);

        $assigneeOnly = User::factory()->create(['name' => 'Assigner Only']);
        $assigneeOnly->givePermissionTo(['tickets.assign', 'tickets.view', 'tickets.update']);
        $assigneeOnly->branches()->attach($branch->id);

        $this->assertFalse($assigneeOnly->can('users.view'));

        $this->actingAsUser($assigneeOnly);
        $this->getJson('/api/v1/pickers/users?q=Assigner')
            ->assertOk()
            ->assertJsonFragment(['id' => $assigneeOnly->id]);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_system_version_endpoint(): void
    {
        $branch = $this->makeBranch(['code' => 'VER']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $this->getJson('/api/v1/system/version')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'app_name',
                    'stage',
                    'git_sha',
                    'commit_sha',
                    'branch',
                    'build_timestamp',
                    'deployment_timestamp',
                    'backend_version',
                    'frontend_version',
                    'migration_batch',
                    'latest_migration',
                    'php_version',
                    'laravel_version',
                ],
            ])
            ->assertJsonPath('data.backend_version', '7.1')
            ->assertJsonPath('data.stage', '10.2-production-recovery');

        $health = $this->getJson('/api/v1/health');
        $this->assertContains($health->status(), [200, 503]);
        $this->assertArrayHasKey('deployment', $health->json('data'));
        $this->assertSame('10.2-production-recovery', $health->json('data.deployment.stage'));
    }
}
