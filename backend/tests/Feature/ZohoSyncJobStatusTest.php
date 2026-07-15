<?php

namespace Tests\Feature;

use App\Jobs\SyncZohoCustomersJob;
use App\Models\User;
use App\Models\ZohoSyncJob;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithZoho;
use Tests\TestCase;

class ZohoSyncJobStatusTest extends TestCase
{
    use InteractsWithZoho;
    use RefreshDatabase;

    public function test_sync_job_moves_to_completed_with_stats(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response([
                'contacts' => [],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $job = ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_CUSTOMERS,
            'status' => ZohoSyncJob::STATUS_PENDING,
        ]);

        (new SyncZohoCustomersJob($job->id, false))->handle(app(\App\Services\Zoho\ZohoCustomerSyncService::class));

        $job->refresh();
        $this->assertSame(ZohoSyncJob::STATUS_COMPLETED, $job->status);
        $this->assertNotNull($job->started_at);
        $this->assertNotNull($job->finished_at);
        $this->assertSame(0, $job->stats['processed']);
    }

    public function test_api_queues_sync_job(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->createConnectedZoho();

        Http::fake([
            'https://www.zohoapis.com/books/v3/contacts*' => Http::response([
                'contacts' => [],
                'page_context' => ['has_more_page' => false],
            ], 200),
        ]);

        $user = User::factory()->create();
        $user->assignRole(User::ROLE_CENTRAL_FINANCE);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/zoho/sync/customers')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        $this->assertDatabaseHas('zoho_sync_jobs', [
            'type' => ZohoSyncJob::TYPE_CUSTOMERS,
        ]);
    }
}
