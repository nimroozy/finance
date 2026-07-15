<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_branch_manager_cannot_access_other_branch_by_id(): void
    {
        $branchA = Branch::factory()->create(['code' => 'A-001']);
        $branchB = Branch::factory()->create(['code' => 'B-001']);

        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_BRANCH_MANAGER);
        $manager->branches()->attach($branchA->id);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/branches/'.$branchA->id)
            ->assertOk()
            ->assertJsonPath('data.code', 'A-001');

        $this->getJson('/api/v1/branches/'.$branchB->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_super_admin_can_access_all_branches(): void
    {
        $branchA = Branch::factory()->create(['code' => 'A-001']);
        $branchB = Branch::factory()->create(['code' => 'B-001']);

        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/branches/'.$branchB->id)
            ->assertOk()
            ->assertJsonPath('data.code', 'B-001');

        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
