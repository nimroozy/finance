<?php

namespace Tests\Feature;

use App\Models\Tickets\Ticket;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7SlaPolicySeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage71TransitionsTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage7SlaPolicySeeder::class);
        $this->seed(Stage7TicketTypeSeeder::class);
        Http::fake();
    }

    public function test_transition_rejects_disallowed_status_with_clear_message(): void
    {
        $branch = $this->makeBranch(['code' => 'TR71']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Transition clarity',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $response = $this->postJson("/api/v1/tickets/{$id}/transition", [
            'status' => Ticket::STATUS_CLOSED,
        ])->assertStatus(422);

        $this->assertStringContainsString('Invalid ticket status transition', $response->json('message'));
        $this->assertStringContainsString('Allowed:', $response->json('message'));
    }

    public function test_allowed_transition_succeeds_and_returns_new_transitions(): void
    {
        $branch = $this->makeBranch(['code' => 'TR72']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Happy path',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $this->postJson("/api/v1/tickets/{$id}/transition", [
            'status' => Ticket::STATUS_TRIAGED,
            'comment' => 'Classified',
        ])->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_TRIAGED)
            ->assertJsonStructure(['data' => ['allowed_transitions']]);

        $transitions = $this->getJson("/api/v1/tickets/{$id}")->json('data.allowed_transitions');
        $statuses = collect($transitions)->pluck('status')->all();
        $this->assertContains(Ticket::STATUS_ASSIGNED, $statuses);
        $this->assertContains(Ticket::STATUS_IN_PROGRESS, $statuses);
    }

    public function test_cancel_requires_reason(): void
    {
        $branch = $this->makeBranch(['code' => 'TR73']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Cancel me',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $this->postJson("/api/v1/tickets/{$id}/transition", [
            'status' => Ticket::STATUS_CANCELLED,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'A reason is required to transition to [cancelled].');

        $this->postJson("/api/v1/tickets/{$id}/transition", [
            'status' => Ticket::STATUS_CANCELLED,
            'reason' => 'Duplicate',
        ])->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_CANCELLED);
    }
}
