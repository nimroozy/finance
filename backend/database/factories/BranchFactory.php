<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('BR-###'));

        return [
            'code' => $code,
            'name_en' => fake()->city().' Branch',
            'name_fa' => 'شعبه '.fake()->city(),
            'province_en' => fake()->state(),
            'province_fa' => fake()->state(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'receipt_prefix' => substr($code, 0, 6),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
