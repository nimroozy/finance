<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'collector_id' => Collector::factory(),
            'created_by' => User::factory(),
            'branch_id' => Branch::factory(),
            'payment_method_id' => fn () => PaymentMethod::query()->where('code', 'cash')->value('id')
                ?? PaymentMethod::query()->value('id'),
            'currency' => 'AFN',
            'amount' => '100.0000',
            'status' => Payment::STATUS_DRAFT,
            'zoho_sync_status' => Payment::ZOHO_PENDING,
            'idempotency_key' => (string) Str::uuid(),
            'draft_expires_at' => now()->addDay(),
        ];
    }
}
