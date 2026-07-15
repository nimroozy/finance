<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Collector;
use App\Models\CollectorWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectorWallet>
 */
class CollectorWalletFactory extends Factory
{
    protected $model = CollectorWallet::class;

    public function definition(): array
    {
        return [
            'collector_id' => Collector::factory(),
            'branch_id' => Branch::factory(),
            'currency' => 'AFN',
            'balance' => '0.0000',
            'pending_handover_balance' => '0.0000',
        ];
    }
}
