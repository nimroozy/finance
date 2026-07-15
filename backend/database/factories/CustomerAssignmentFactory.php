<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAssignment>
 */
class CustomerAssignmentFactory extends Factory
{
    protected $model = CustomerAssignment::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'collector_id' => Collector::factory(),
            'assigned_by' => User::factory(),
            'status' => CustomerAssignment::STATUS_ASSIGNED,
            'is_active' => true,
            'is_primary' => true,
            'priority' => CustomerAssignment::PRIORITY_NORMAL,
            'assignment_source' => 'manual',
            'debt_snapshot_outstanding' => fake()->randomFloat(4, 100, 20000),
            'debt_snapshot_overdue' => null,
            'debt_snapshot_invoice_count' => fake()->numberBetween(1, 10),
            'debt_snapshot_currency' => 'AFN',
            'due_date' => now()->addDays(7)->toDateString(),
            'notes' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CustomerAssignment::STATUS_CANCELLED,
            'is_active' => false,
            'closed_at' => now(),
            'cancel_reason' => 'Cancelled in factory',
        ]);
    }
}
