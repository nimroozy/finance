<?php

namespace Database\Factories;

use App\Models\Collector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collector>
 */
class CollectorFactory extends Factory
{
    protected $model = Collector::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => fake()->unique()->bothify('EMP-###'),
            'max_active_assignments' => 50,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
