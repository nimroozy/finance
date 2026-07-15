<?php

namespace Tests\Feature;

use App\Models\CollectionRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class RouteWorkflowTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_route_publish_start_complete_workflow(): void
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

        $route = $this->postJson('/api/v1/routes', [
            'branch_id' => $branch->id,
            'collector_id' => $collector->id,
            'name' => 'Morning Kabul',
            'route_date' => now()->toDateString(),
            'stops' => [
                ['customer_id' => $customer->id, 'sequence' => 1],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(CollectionRoute::STATUS_DRAFT, $route['status']);

        $this->postJson('/api/v1/routes/'.$route['id'].'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', CollectionRoute::STATUS_PUBLISHED);

        $this->actingAsUser($collectorUser);

        $this->postJson('/api/v1/routes/'.$route['id'].'/start')
            ->assertOk()
            ->assertJsonPath('data.status', CollectionRoute::STATUS_IN_PROGRESS);

        $this->postJson('/api/v1/routes/'.$route['id'].'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', CollectionRoute::STATUS_COMPLETED);
    }

    public function test_cannot_publish_empty_route(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);

        $this->actingAsUser($manager);

        $route = $this->postJson('/api/v1/routes', [
            'branch_id' => $branch->id,
            'collector_id' => $collector->id,
            'name' => 'Empty',
            'route_date' => now()->toDateString(),
            'stops' => [],
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/routes/'.$route['id'].'/publish')->assertStatus(422);
    }
}
