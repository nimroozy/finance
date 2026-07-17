<?php

namespace Tests\Feature;

use App\Jobs\EvaluateCrmFollowUpsJob;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Crm\Quotation;
use App\Models\Payment;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Services\Crm\FollowUpService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage8LeadSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage8CrmTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage8LeadSourceSeeder::class);
        Http::fake();
    }

    public function test_lead_create_and_branch_isolation(): void
    {
        $branchA = $this->makeBranch(['code' => 'CRA']);
        $branchB = $this->makeBranch(['code' => 'CRB']);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);

        $this->actingAsUser($managerA);
        $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branchA->id,
            'contact_person' => 'Ali',
            'phone' => '0700000001',
            'source_code' => 'whatsapp',
        ])->assertCreated()
            ->assertJsonPath('data.pipeline_stage', Lead::STAGE_LEAD)
            ->assertJsonPath('data.branch_id', $branchA->id);

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/crm/leads')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsUser($managerA);
        $this->getJson('/api/v1/crm/leads')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_pipeline_transition_valid_and_invalid(): void
    {
        $branch = $this->makeBranch(['code' => 'PIP']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Sara',
            'phone' => '0700000002',
            'source_code' => 'walk_in',
        ])->json('data.id');

        $this->postJson("/api/v1/crm/leads/{$id}/transition", [
            'pipeline_stage' => Lead::STAGE_CONTACTED,
        ])->assertOk()
            ->assertJsonPath('data.pipeline_stage', Lead::STAGE_CONTACTED);

        $this->postJson("/api/v1/crm/leads/{$id}/transition", [
            'pipeline_stage' => Lead::STAGE_ACTIVE_CUSTOMER,
        ])->assertStatus(422);
    }

    public function test_duplicate_phone_detection(): void
    {
        $branch = $this->makeBranch(['code' => 'DUP']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'First',
            'phone' => '0700111222',
            'source_code' => 'referral',
        ])->assertCreated();

        $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Second',
            'phone' => '0700111222',
            'source_code' => 'campaign',
        ])->assertCreated()
            ->assertJsonPath('data.duplicate_warning.0.phone', '0700111222');
    }

    public function test_activity_and_follow_up(): void
    {
        $branch = $this->makeBranch(['code' => 'ACT']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $leadId = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Act Lead',
            'phone' => '0700333444',
            'source_code' => 'phone_inbound',
        ])->json('data.id');

        $this->postJson('/api/v1/crm/activities', [
            'lead_id' => $leadId,
            'type' => 'call',
            'subject' => 'Intro call',
            'body' => 'Discussed package',
        ])->assertCreated()
            ->assertJsonPath('data.type', 'call');

        $this->postJson('/api/v1/crm/follow-ups', [
            'lead_id' => $leadId,
            'due_at' => now()->addDay()->toIso8601String(),
            'notes' => 'Call back',
        ])->assertCreated()
            ->assertJsonPath('data.status', FollowUp::STATUS_PENDING);
    }

    public function test_coverage_and_survey_creates_task(): void
    {
        $branch = $this->makeBranch(['code' => 'SRV']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $leadId = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Survey Lead',
            'phone' => '0700555666',
            'address' => 'Kabul',
            'source_code' => 'whatsapp',
        ])->json('data.id');

        $this->postJson("/api/v1/crm/leads/{$leadId}/transition", [
            'pipeline_stage' => Lead::STAGE_CONTACTED,
        ])->assertOk();
        $this->postJson("/api/v1/crm/leads/{$leadId}/transition", [
            'pipeline_stage' => Lead::STAGE_QUALIFIED,
        ])->assertOk();

        $this->postJson('/api/v1/crm/coverage-checks', [
            'lead_id' => $leadId,
            'result' => 'survey_required',
            'candidate_tower' => 'TW-1',
            'distance_m' => 1200,
        ])->assertCreated();

        $survey = $this->postJson('/api/v1/crm/site-surveys', [
            'lead_id' => $leadId,
            'los_status' => 'clear',
            'survey_date' => now()->toDateString(),
        ])->assertCreated()
            ->json('data');

        $this->assertNotNull($survey['task_id']);
        $this->assertTrue(Task::query()->whereKey($survey['task_id'])->exists());
    }

    public function test_quotation_accept(): void
    {
        $branch = $this->makeBranch(['code' => 'QTE']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $leadId = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Quote Lead',
            'phone' => '0700777888',
            'source_code' => 'website',
            'pipeline_stage' => Lead::STAGE_QUOTATION,
        ])->json('data.id');

        $quoteId = $this->postJson('/api/v1/crm/quotations', [
            'lead_id' => $leadId,
            'monthly_package' => 1500,
            'installation_fee' => 500,
        ])->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/crm/quotations/{$quoteId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', Quotation::STATUS_ACCEPTED);

        $this->assertSame(Lead::STAGE_APPROVED, Lead::query()->find($leadId)?->pipeline_stage);
    }

    public function test_conversion_creates_installation(): void
    {
        $branch = $this->makeBranch(['code' => 'CNV']);
        $manager = $this->makeManager($branch);
        $paymentCountBefore = Payment::query()->count();

        $this->actingAsUser($manager);
        $leadId = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Convert Me',
            'company' => 'Acme ISP',
            'phone' => '0700999000',
            'address' => 'Herat',
            'interested_package' => '20Mbps',
            'estimated_mrr' => 2000,
            'expected_installation_fee' => 800,
            'source_code' => 'referral',
            'pipeline_stage' => Lead::STAGE_APPROVED,
        ])->json('data.id');

        $response = $this->postJson("/api/v1/crm/leads/{$leadId}/convert", [
            'win_reason' => 'signed',
        ])->assertOk();

        $installationId = $response->json('data.installation_id');
        $this->assertNotNull($installationId);
        $this->assertTrue(Installation::query()->whereKey($installationId)->exists());

        $lead = Lead::query()->findOrFail($leadId);
        $this->assertNotNull($lead->converted_customer_id);
        $this->assertSame(Lead::STAGE_INSTALLATION_REQUEST, $lead->pipeline_stage);

        $this->assertSame($paymentCountBefore, Payment::query()->count());
    }

    public function test_permissions_denial(): void
    {
        $branch = $this->makeBranch(['code' => 'DEN']);
        [$collector] = $this->makeCollectorUser($branch);

        $this->actingAsUser($collector);
        $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Nope',
            'phone' => '0700123456',
        ])->assertForbidden();

        $this->getJson('/api/v1/crm/leads')->assertForbidden();
    }

    public function test_follow_up_overdue_job(): void
    {
        $branch = $this->makeBranch(['code' => 'OVD']);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $leadId = $this->postJson('/api/v1/crm/leads', [
            'branch_id' => $branch->id,
            'contact_person' => 'Overdue Lead',
            'phone' => '0700666777',
            'source_code' => 'other',
        ])->json('data.id');

        $followUpId = $this->postJson('/api/v1/crm/follow-ups', [
            'lead_id' => $leadId,
            'due_at' => now()->subHour()->toIso8601String(),
            'notes' => 'Already late',
        ])->json('data.id');

        (new EvaluateCrmFollowUpsJob)->handle(app(FollowUpService::class));

        $this->assertSame(FollowUp::STATUS_OVERDUE, FollowUp::query()->find($followUpId)?->status);
    }
}
