<?php

namespace Tests\Feature;

use App\Jobs\UpdatePromiseStatusesJob;
use App\Models\PromiseToPay;
use App\Services\Promises\PromiseToPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class PromiseToPayTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_collector_can_create_promise(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->assertCreated();

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/promises', [
            'customer_id' => $customer->id,
            'amount' => 1000,
            'promised_date' => now()->addWeek()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', PromiseToPay::STATUS_ACTIVE);
    }

    public function test_rejects_zero_amount(): void
    {
        $branch = $this->makeBranch();
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $manager = $this->makeManager($branch);
        $this->actingAsUser($manager);
        $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ]);

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/promises', [
            'customer_id' => $customer->id,
            'amount' => 0,
            'promised_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(422);
    }

    public function test_status_job_marks_overdue(): void
    {
        $branch = $this->makeBranch();
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $manager = $this->makeManager($branch);

        $promise = PromiseToPay::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'created_by' => $manager->id,
            'promised_date' => now()->subDays(2)->toDateString(),
            'status' => PromiseToPay::STATUS_ACTIVE,
        ]);

        (new UpdatePromiseStatusesJob)->handle(app(PromiseToPayService::class));

        $this->assertSame(PromiseToPay::STATUS_OVERDUE, $promise->fresh()->status);
    }

    public function test_manager_can_fulfill_promise(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $promise = PromiseToPay::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
            'created_by' => $manager->id,
            'status' => PromiseToPay::STATUS_ACTIVE,
            'promised_date' => now()->addDays(1)->toDateString(),
        ]);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/promises/'.$promise->id.'/fulfill')
            ->assertOk()
            ->assertJsonPath('data.status', PromiseToPay::STATUS_FULFILLED);
    }
}
