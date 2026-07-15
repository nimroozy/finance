<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\PromiseToPay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromiseToPay>
 */
class PromiseToPayFactory extends Factory
{
    protected $model = PromiseToPay::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'assignment_id' => null,
            'visit_id' => null,
            'collector_id' => Collector::factory(),
            'created_by' => User::factory(),
            'amount' => fake()->randomFloat(4, 50, 5000),
            'currency' => 'AFN',
            'promised_date' => now()->addDays(5)->toDateString(),
            'status' => PromiseToPay::STATUS_ACTIVE,
            'notes' => null,
        ];
    }
}
