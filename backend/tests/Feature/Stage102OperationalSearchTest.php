<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage102OperationalSearchTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage7OrgSeeder::class);
        $this->seed(Stage7TicketTypeSeeder::class);
    }

    public function test_search_is_branch_isolated(): void
    {
        $branchA = $this->makeBranch(['code' => 'SRCH-A']);
        $branchB = $this->makeBranch(['code' => 'SRCH-B']);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);

        $customerA = $this->makeCustomer($branchA, [
            'contact_name' => 'UniqueAlphaCustomer',
            'phone' => '0700111000',
            'zoho_contact_id' => 'ZOHO-ALPHA',
        ]);
        $customerB = $this->makeCustomer($branchB, [
            'contact_name' => 'UniqueAlphaCustomer',
            'phone' => '0700222000',
            'zoho_contact_id' => 'ZOHO-BETA',
        ]);

        $this->actingAsUser($managerA);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branchA->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'UniqueAlphaCustomer outage',
            'customer_id' => $customerA->id,
            'source' => 'manual',
            'priority' => 'normal',
        ])->assertCreated();

        $this->actingAsUser($managerB);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branchB->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'UniqueAlphaCustomer outage',
            'customer_id' => $customerB->id,
            'source' => 'manual',
            'priority' => 'normal',
        ])->assertCreated();

        $this->actingAsUser($managerA);
        $data = $this->getJson('/api/v1/operations/search?q=UniqueAlphaCustomer')
            ->assertOk()
            ->json('data');

        $customerIds = collect($data['customers'] ?? [])->pluck('id')->all();
        $this->assertContains($customerA->id, $customerIds);
        $this->assertNotContains($customerB->id, $customerIds);

        $ticketSubjects = collect($data['tickets'] ?? [])->pluck('subject')->all();
        $this->assertContains('UniqueAlphaCustomer outage', $ticketSubjects);
        // Only branch A tickets should appear for manager A
        $ticketBranchIds = collect($data['tickets'] ?? [])->pluck('branch_id')->unique()->values()->all();
        $this->assertSame([$branchA->id], $ticketBranchIds);

        // Zoho id for the other branch must not leak
        $zoho = $this->getJson('/api/v1/operations/search?q=ZOHO-BETA')->assertOk()->json('data');
        $this->assertSame([], $zoho['customers'] ?? []);
    }

    public function test_search_covers_phone_and_entity_groups(): void
    {
        $branch = $this->makeBranch(['code' => 'SRCH-P']);
        $manager = $this->makeManager($branch);
        $this->makeCustomer($branch, [
            'contact_name' => 'PhoneTarget',
            'phone' => '0799123456',
        ]);

        $this->actingAsUser($manager);
        $data = $this->getJson('/api/v1/operations/search?q=0799123456')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data['customers']);
        foreach ([
            'services', 'leads', 'tickets', 'tasks', 'installations', 'payments',
            'products', 'equipment', 'transfers', 'sites', 'towers', 'users',
        ] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }
}
