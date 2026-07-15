<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class AssignmentAuthorizationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_collector_cannot_create_assignment(): void
    {
        $branch = $this->makeBranch();
        [$collectorUser] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        [, $collector] = $this->makeCollectorUser($branch);

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertStatus(403);
    }

    public function test_auditor_can_view_but_not_manage_assignments(): void
    {
        $this->seedRoles();
        $branch = $this->makeBranch();
        $auditor = User::factory()->create();
        $auditor->assignRole(User::ROLE_AUDITOR);
        $this->actingAsUser($auditor);

        $this->getJson('/api/v1/assignments')->assertOk();
        $this->postJson('/api/v1/assignments', [
            'customer_id' => 1,
            'collector_id' => 1,
        ])->assertStatus(403);
    }

    public function test_branch_manager_can_manage_assignments(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }
}
