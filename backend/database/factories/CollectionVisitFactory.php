<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionVisit>
 */
class CollectionVisitFactory extends Factory
{
    protected $model = CollectionVisit::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'assignment_id' => null,
            'collector_id' => Collector::factory(),
            'route_stop_id' => null,
            'recorded_by' => User::factory(),
            'visited_at' => now(),
            'outcome' => CollectionVisit::OUTCOME_CUSTOMER_MET,
            'notes' => fake()->optional()->sentence(),
            'follow_up_required' => false,
            'follow_up_date' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'distance_meters' => null,
            'gps_risk_level' => CollectionVisit::GPS_OK,
        ];
    }

    public function forAssignment(CustomerAssignment $assignment): static
    {
        return $this->state(fn () => [
            'branch_id' => $assignment->branch_id,
            'customer_id' => $assignment->customer_id,
            'assignment_id' => $assignment->id,
            'collector_id' => $assignment->collector_id,
        ]);
    }
}
