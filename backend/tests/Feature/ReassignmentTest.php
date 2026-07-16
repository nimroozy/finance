<?php

namespace Tests\Feature;

use App\Models\CustomerAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class ReassignmentTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_reassign_closes_old_and_creates_new_active(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collectorA] = $this->makeCollectorUser($branch);
        [, $collectorB] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $created = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collectorA->id,
        ])->assertCreated()->json('data.assignment');

        $new = $this->postJson('/api/v1/assignments/'.$created['id'].'/reassign', [
            'collector_id' => $collectorB->id,
            'notes' => 'Workload balance',
        ])->assertOk()->json('data');

        $this->assertSame($collectorB->id, $new['collector_id']);
        $this->assertTrue($new['is_active']);

        $old = CustomerAssignment::withoutGlobalScopes()->find($created['id']);
        $this->assertFalse($old->is_active);
        $this->assertSame(CustomerAssignment::STATUS_REASSIGNED, $old->status);

        $this->assertSame(1, CustomerAssignment::withoutGlobalScopes()->where('customer_id', $customer->id)->where('is_active', true)->count());
    }

    public function test_cancel_deactivates_assignment(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $created = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->postJson('/api/v1/assignments/'.$created['id'].'/cancel', [
            'reason' => 'Customer relocated',
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', CustomerAssignment::STATUS_CANCELLED);
    }
}
