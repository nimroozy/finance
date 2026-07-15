<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class AssignmentCreationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_creates_assignment_with_debt_snapshot(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['outstanding_receivable' => '2500.5000']);

        $this->actingAsUser($manager);

        $response = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'priority' => 'high',
        ])->assertCreated();

        $response->assertJsonPath('data.assignment.status', CustomerAssignment::STATUS_ASSIGNED);
        $response->assertJsonPath('data.assignment.is_active', true);
        $response->assertJsonPath('data.assignment.debt_snapshot_outstanding', '2500.5000');

        $this->assertDatabaseHas('customer_assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'is_active' => 1,
        ]);
    }

    public function test_rejects_second_active_assignment_for_same_customer(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collectorA] = $this->makeCollectorUser($branch);
        [, $collectorB] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collectorA->id,
        ])->assertCreated();

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collectorB->id,
        ])->assertStatus(422);
    }

    public function test_rejects_unmapped_customer(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, [
            'is_unmapped' => true,
            'status' => Customer::STATUS_UNMAPPED,
            'branch_id' => null,
        ]);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertStatus(422);
    }

    public function test_warns_when_balance_is_zero(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, ['outstanding_receivable' => '0.0000']);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertCreated()
            ->assertJsonPath('data.warnings.0', 'Customer outstanding balance is zero.');
    }
}
