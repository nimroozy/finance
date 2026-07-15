<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'receipt_number' => 'TST-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'payment_id' => Payment::factory(),
            'branch_id' => Branch::factory(),
            'verification_token' => Str::random(48),
            'status' => Receipt::STATUS_ISSUED,
            'language' => 'en',
            'template_version' => '1.0',
            'issued_at' => now(),
        ];
    }
}
