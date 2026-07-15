<?php

namespace Tests\Feature;

use App\Models\CollectionVisit;
use App\Models\PromiseToPay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class VisitCreationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_collector_can_create_visit_on_assignment(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, [
            'latitude' => '34.5553000',
            'longitude' => '69.2075000',
        ]);

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
            'notes' => 'Spoke with debtor',
            'latitude' => 34.5554,
            'longitude' => 69.2076,
        ])->assertCreated()
            ->assertJsonPath('data.outcome', CollectionVisit::OUTCOME_CUSTOMER_MET)
            ->assertJsonPath('data.gps_risk_level', CollectionVisit::GPS_OK);
    }

    public function test_promise_outcome_creates_promise(): void
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
            'promise' => [
                'amount' => 500,
                'promised_date' => now()->addDays(3)->toDateString(),
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('promise_to_pay', [
            'customer_id' => $customer->id,
            'status' => PromiseToPay::STATUS_ACTIVE,
        ]);
    }
}
