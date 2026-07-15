<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPaymentFixtures;
use Tests\TestCase;

class PaymentAuthorizationTest extends TestCase
{
    use CreatesPaymentFixtures, RefreshDatabase;

    public function test_collector_cannot_see_other_collector_payments(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        [$collectorAUser, $collectorA] = $this->makeCollectorUser($branch);
        [$collectorBUser, $collectorB] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($manager, $customer, $collectorA);

        $this->actingAsUser($collectorAUser);
        $payload = $this->draftPaymentPayload($customer, $collectorA, $invoice, $this->cashMethod());
        $draft = $this->postJson('/api/v1/payments/draft', $payload)->assertCreated()->json('data');

        $this->actingAsUser($collectorBUser);
        $this->getJson('/api/v1/payments/'.$draft['uuid'])->assertNotFound();
    }

    public function test_auditor_can_view_but_not_create(): void
    {
        $branch = $this->makeBranch();
        $this->seedRoles();
        $auditor = User::factory()->create();
        $auditor->assignRole(User::ROLE_AUDITOR);
        $auditor->branches()->attach($branch->id);

        $this->actingAsUser($auditor);
        $this->getJson('/api/v1/payments')->assertOk();
        $this->postJson('/api/v1/payments/preview', [])->assertForbidden();
    }

    public function test_branch_isolation_for_managers(): void
    {
        $branchA = $this->makeBranch(['code' => 'BA']);
        $branchB = $this->makeBranch(['code' => 'BB']);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        [$collectorUser, $collector] = $this->makeCollectorUser($branchA);
        $customer = $this->makeCustomer($branchA);
        $invoice = $this->makeInvoiceForCustomer($customer);
        $this->makeActiveAssignment($managerA, $customer, $collector);

        $this->actingAsUser($collectorUser);
        $draft = $this->postJson('/api/v1/payments/draft', $this->draftPaymentPayload($customer, $collector, $invoice, $this->cashMethod()))
            ->assertCreated()
            ->json('data');

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/payments/'.$draft['uuid'])->assertNotFound();
    }
}
