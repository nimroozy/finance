<?php

namespace Tests\Feature;

use App\Models\CustomerAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class CollectorIsolationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_collector_only_sees_own_active_assignments_and_customers(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$userA, $collectorA] = $this->makeCollectorUser($branch);
        [$userB, $collectorB] = $this->makeCollectorUser($branch);
        $customerA = $this->makeCustomer($branch, ['contact_name' => 'Assigned A']);
        $customerB = $this->makeCustomer($branch, ['contact_name' => 'Assigned B']);
        $unassigned = $this->makeCustomer($branch, ['contact_name' => 'Unassigned']);

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customerA->id,
            'collector_id' => $collectorA->id,
        ])->assertCreated();
        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customerB->id,
            'collector_id' => $collectorB->id,
        ])->assertCreated();

        $this->actingAsUser($userA);

        $assignments = $this->getJson('/api/v1/assignments')->assertOk()->json('data');
        $this->assertCount(1, $assignments);
        $this->assertSame($collectorA->id, $assignments[0]['collector_id']);

        $customers = $this->getJson('/api/v1/customers')->assertOk()->json('data');
        $names = collect($customers)->pluck('contact_name')->all();
        $this->assertContains('Assigned A', $names);
        $this->assertNotContains('Assigned B', $names);
        $this->assertNotContains('Unassigned', $names);

        $otherAssignment = CustomerAssignment::withoutGlobalScopes()
            ->where('collector_id', $collectorB->id)
            ->first();

        $this->getJson('/api/v1/assignments/'.$otherAssignment->id)->assertStatus(404);
    }

    public function test_collector_can_accept_own_assignment(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$userA, $collectorA] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collectorA->id,
        ])->json('data.assignment');

        $this->actingAsUser($userA);
        $this->postJson('/api/v1/assignments/'.$assignment['id'].'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', CustomerAssignment::STATUS_ACCEPTED);
    }
}
