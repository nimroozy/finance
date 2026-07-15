<?php

namespace Tests\Feature;

use App\Models\CollectionVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class VisitFileUploadTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    public function test_collector_can_upload_and_download_evidence(): void
    {
        Storage::fake('local');

        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorUser, $collector] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $assignment = $this->postJson('/api/v1/assignments', [
            'customer_id' => $customer->id,
            'collector_id' => $collector->id,
        ])->json('data.assignment');

        $this->actingAsUser($collectorUser);

        $visit = $this->postJson('/api/v1/visits', [
            'assignment_id' => $assignment['id'],
            'customer_id' => $customer->id,
            'outcome' => CollectionVisit::OUTCOME_CUSTOMER_MET,
        ])->json('data');

        $file = UploadedFile::fake()->image('evidence.jpg');

        $uploaded = $this->post('/api/v1/visits/'.$visit['id'].'/files', [
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        Storage::disk('local')->assertExists($uploaded['path']);

        $this->getJson('/api/v1/files/'.$uploaded['id'].'/download')->assertOk();
    }
}
