<?php

namespace Tests\Feature;

use App\Models\CollectionVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class VisitValidationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_promise_outcome_requires_promise_payload(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/visits', [
            'assignment_id' => $assignment['id'],
            'customer_id' => $customer->id,
            'outcome' => CollectionVisit::OUTCOME_PROMISE_TO_PAY,
        ])->assertStatus(422);
    }

    public function test_follow_up_requires_date(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/visits', [
            'assignment_id' => $assignment['id'],
            'customer_id' => $customer->id,
            'outcome' => CollectionVisit::OUTCOME_FOLLOW_UP,
            'follow_up_required' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['follow_up_date']);
    }

    public function test_wrong_address_requires_notes(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/visits', [
            'assignment_id' => $assignment['id'],
            'customer_id' => $customer->id,
            'outcome' => CollectionVisit::OUTCOME_WRONG_ADDRESS,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    public function test_gps_denied_still_allows_visit(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/visits', [
            'assignment_id' => $assignment['id'],
            'customer_id' => $customer->id,
            'outcome' => CollectionVisit::OUTCOME_CUSTOMER_MET,
            'gps_permission_state' => 'denied',
            'latitude' => 34.5,
            'longitude' => 69.1,
        ])->assertCreated()
            ->assertJsonPath('data.gps_permission_state', 'denied')
            ->assertJsonPath('data.latitude', null);
    }
}
