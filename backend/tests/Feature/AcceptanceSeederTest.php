<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Models\Services\ServiceChangeRequest;
use App\Models\User;
use Database\Seeders\AcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_seeder_creates_prefixed_users_branch_and_fixtures(): void
    {
        $this->seed(AcceptanceSeeder::class);

        $branch = Branch::query()->withoutGlobalScopes()->where('code', AcceptanceSeeder::BRANCH_CODE)->first();
        $this->assertNotNull($branch);

        foreach (['ACCEPTANCE-admin', 'ACCEPTANCE-manager', 'ACCEPTANCE-sales', 'ACCEPTANCE-noc'] as $username) {
            $user = User::query()->where('username', $username)->first();
            $this->assertNotNull($user, $username);
            $this->assertTrue($user->branches()->whereKey($branch->id)->exists());
        }

        $customer = Customer::query()->withoutGlobalScopes()
            ->where('zoho_contact_id', AcceptanceSeeder::ZOHO_CONTACT_ID)
            ->first();
        $this->assertNotNull($customer);

        $lead = Lead::query()->withoutGlobalScopes()
            ->where('lead_number', 'ACC-LEAD-000001')
            ->first();
        $this->assertNotNull($lead);
        $this->assertSame(Lead::STAGE_APPROVED, $lead->pipeline_stage);

        $this->assertTrue(
            ServiceChangeRequest::query()->where('reason', 'ACCEPTANCE change request fixture')->exists()
        );

        $manager = User::query()->where('username', 'ACCEPTANCE-manager')->firstOrFail();
        $this->assertTrue($manager->can('services.activate.override'));
    }
}
