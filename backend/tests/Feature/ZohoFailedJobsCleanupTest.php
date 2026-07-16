<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZohoFailedJobsCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_preserves_failed_jobs(): void
    {
        Storage::fake('zoho_reports');
        foreach (range(1, 3) as $index) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => '{"job":"SyncZohoCustomersJob"}',
                'exception' => 'Zoho invalid last_modified_time timestamp '.$index,
                'failed_at' => now(),
            ]);
        }

        $this->artisan('zoho:failed-jobs-cleanup')->assertSuccessful();

        $this->assertDatabaseCount('failed_jobs', 3);
        $this->assertCount(1, Storage::disk('zoho_reports')->files('zoho-reports'));
    }
}
