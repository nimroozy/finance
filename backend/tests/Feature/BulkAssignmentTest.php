<?php

namespace Tests\Feature;

use App\Models\CustomerAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class BulkAssignmentTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_bulk_assign_sync_for_small_batches(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $c1 = $this->makeCustomer($branch);
        $c2 = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $this->postJson('/api/v1/assignments/bulk', [
            'customer_ids' => [$c1->id, $c2->id],
            'collector_id' => $collector->id,
            'branch_id' => $branch->id,
        ])->assertCreated()
            ->assertJsonPath('data.queued', false);

        $this->assertSame(2, CustomerAssignment::withoutGlobalScopes()->where('is_active', true)->count());
    }

    public function test_auto_preview_and_confirm(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [, $collector] = $this->makeCollectorUser($branch);
        $c1 = $this->makeCustomer($branch);
        $c2 = $this->makeCustomer($branch);

        $this->actingAsUser($manager);

        $preview = $this->postJson('/api/v1/assignments/auto-preview', [
            'branch_id' => $branch->id,
            'strategy' => 'least_loaded',
            'collector_ids' => [$collector->id],
        ])->assertCreated()
            ->json('data');

        $this->assertSame(2, $preview['selected_count']);

        $this->postJson('/api/v1/assignments/auto-confirm', [
            'operation_id' => $preview['id'],
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('customer_assignments', [
            'customer_id' => $c1->id,
            'collector_id' => $collector->id,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('customer_assignments', [
            'customer_id' => $c2->id,
            'collector_id' => $collector->id,
            'is_active' => 1,
        ]);
    }
}
