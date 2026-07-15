<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\CollectionRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionRoute>
 */
class CollectionRouteFactory extends Factory
{
    protected $model = CollectionRoute::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'collector_id' => Collector::factory(),
            'created_by' => User::factory(),
            'name' => 'Route '.fake()->streetName(),
            'route_date' => now()->toDateString(),
            'status' => CollectionRoute::STATUS_DRAFT,
            'notes' => null,
        ];
    }
}
