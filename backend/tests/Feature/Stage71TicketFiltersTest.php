<?php

namespace Tests\Feature;

use App\Models\Tickets\OperationalAttachment;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketSlaState;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7SlaPolicySeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage71TicketFiltersTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage7SlaPolicySeeder::class);
        $this->seed(Stage7TicketTypeSeeder::class);
        Http::fake();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_ticket_index_filters_priority_search_assignee_and_unassigned(): void
    {
        $branch = $this->makeBranch(['code' => 'F71']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        [$tech] = $this->makeCollectorUser($branch);
        $customer = $this->makeCustomer($branch, [
            'contact_name' => 'Ali Karimi',
            'customer_number' => 'C-9001',
            'phone' => '0700111222',
        ]);

        $this->actingAsUser($manager);

        $high = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'High priority bill',
            'customer_id' => $customer->id,
            'priority' => Ticket::PRIORITY_HIGH,
        ])->json('data.id');

        $low = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Low priority bill',
            'customer_id' => $customer->id,
            'priority' => Ticket::PRIORITY_LOW,
        ])->json('data.id');

        $this->postJson("/api/v1/tickets/{$high}/assign", ['user_id' => $tech->id])->assertOk();

        $this->getJson('/api/v1/tickets?priority=high')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $high);

        $this->getJson('/api/v1/tickets?search=Ali')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/tickets?search=C-9001')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/tickets?assignee_id='.$tech->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $high);

        $this->getJson('/api/v1/tickets?unassigned=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $low);

        $this->assertIsArray($this->getJson('/api/v1/tickets')->json('meta.filters'));
    }

    public function test_ticket_index_sla_state_and_source_filters(): void
    {
        $branch = $this->makeBranch(['code' => 'SLAF']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $ticketId = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'SLA filter',
            'customer_id' => $customer->id,
            'source' => Ticket::SOURCE_WHATSAPP,
        ])->json('data.id');

        TicketSlaState::query()->where('ticket_id', $ticketId)->update([
            'response_state' => 'breached',
            'resolution_state' => 'breached',
            'breached_at' => now(),
        ]);

        $this->getJson('/api/v1/tickets?sla_state=breached')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/tickets?source=whatsapp')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/tickets?source=manual')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_ticket_show_includes_allowed_transitions_and_attachments_list(): void
    {
        config(['ticketing.attachments.disk' => 'local']);

        $branch = $this->makeBranch(['code' => 'ATT']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $ticketId = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Show includes',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $show = $this->getJson("/api/v1/tickets/{$ticketId}")->assertOk();
        $transitions = $show->json('data.allowed_transitions');
        $this->assertIsArray($transitions);
        $this->assertNotEmpty($transitions);
        $this->assertArrayHasKey('status', $transitions[0]);
        $this->assertArrayHasKey('label', $transitions[0]);
        $this->assertArrayHasKey('requires_reason', $transitions[0]);
        $this->assertArrayHasKey('permission', $transitions[0]);
        $statuses = collect($transitions)->pluck('status')->all();
        $this->assertContains(Ticket::STATUS_TRIAGED, $statuses);
        $this->assertNotContains(Ticket::STATUS_CLOSED, $statuses);

        $file = UploadedFile::fake()->image('note.jpg');
        $this->post('/api/v1/attachments', [
            'file' => $file,
            'attachable_type' => 'ticket',
            'attachable_id' => $ticketId,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->getJson("/api/v1/tickets/{$ticketId}/attachments")
            ->assertOk()
            ->assertJsonPath('data.0.original_name', 'note.jpg')
            ->assertJsonStructure(['data' => [['uuid', 'download_url']]]);

        $this->assertSame(1, OperationalAttachment::query()->count());
    }

    public function test_task_index_filters_my_and_unassigned(): void
    {
        $branch = $this->makeBranch(['code' => 'TFIL']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        [$tech] = $this->makeCollectorUser($branch);
        $tech->givePermissionTo(['tasks.view', 'tasks.accept', 'tasks.complete']);

        $this->actingAsUser($manager);
        $unassigned = $this->postJson('/api/v1/tasks', [
            'branch_id' => $branch->id,
            'title' => 'Open task',
            'type' => 'office',
        ])->json('data.id');

        $mine = $this->postJson('/api/v1/tasks', [
            'branch_id' => $branch->id,
            'title' => 'Tech task',
            'type' => 'field',
            'assignee_id' => $tech->id,
        ])->json('data.id');

        $this->getJson('/api/v1/tasks?unassigned=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $unassigned);

        $this->actingAsUser($tech);
        $this->getJson('/api/v1/tasks?my=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $mine);

        $taskShow = $this->getJson("/api/v1/tasks/{$mine}")->assertOk();
        $this->assertIsArray($taskShow->json('data.allowed_transitions'));
    }

    public function test_installation_index_filters(): void
    {
        $branch = $this->makeBranch(['code' => 'INSF']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/installations', [
            'branch_id' => $branch->id,
            'prospect_name' => 'Prospect One',
            'contact_name' => 'Sara',
            'phone' => '0799000111',
        ])->assertCreated();

        $this->getJson('/api/v1/installations?search=Sara')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/installations?branch_id='.$branch->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}
