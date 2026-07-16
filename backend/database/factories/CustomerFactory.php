<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'zoho_contact_id' => fake()->unique()->numerify('ZOHO-#####'),
            'customer_number' => fake()->numerify('C-####'),
            'contact_name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'mobile' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'area' => fake()->optional()->citySuffix(),
            'district' => fake()->optional()->city(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'currency' => 'AFN',
            'outstanding_receivable' => fake()->randomFloat(4, 100, 50000),
            'status' => Customer::STATUS_ACTIVE,
            'sync_status' => 'synced',
            'is_unmapped' => false,
        ];
    }

    public function unmapped(): static
    {
        return $this->state(fn () => [
            'branch_id' => null,
            'is_unmapped' => true,
            'status' => Customer::STATUS_UNMAPPED,
        ]);
    }
}
