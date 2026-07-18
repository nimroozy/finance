<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketIntakeSuggestion;
use App\Models\Tickets\TicketSlaState;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppInboundMessage;
use App\Services\Sla\SlaClockService;
use App\Services\Tasks\TaskAssignmentWorkflowService;
use App\Services\Tasks\TaskService;
use App\Services\Tickets\TicketIntakeService;
use App\Services\Tickets\TicketService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7SlaPolicySeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage7TicketingTest extends TestCase
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

    public function test_ticket_creation_and_branch_isolation(): void
    {
        $branchA = $this->makeBranch(['code' => 'BRA']);
        $branchB = $this->makeBranch(['code' => 'BRB']);
        $this->seed(Stage7OrgSeeder::class);

        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        $customer = $this->makeCustomer($branchA);

        $this->actingAsUser($managerA);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branchA->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Bill question',
            'customer_id' => $customer->id,
            'source' => Ticket::SOURCE_MANUAL,
            'priority' => Ticket::PRIORITY_NORMAL,
        ])->assertCreated()
            ->assertJsonPath('data.status', Ticket::STATUS_NEW)
            ->assertJsonPath('data.branch_id', $branchA->id);

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsUser($managerA);
        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $branch = $this->makeBranch(['code' => 'TRN']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Transition test',
            'customer_id' => $customer->id,
        ])->json('data.id');

        $this->postJson("/api/v1/tickets/{$id}/transition", [
            'status' => Ticket::STATUS_CLOSED,
        ])->assertStatus(422);
    }

    public function test_ticket_assign_and_task_create_accept_complete(): void
    {
        $branch = $this->makeBranch(['code' => 'TSK']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        [$tech] = $this->makeCollectorUser($branch);
        $tech->givePermissionTo(['tasks.view', 'tasks.accept', 'tasks.complete', 'tasks.assign']);

        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $ticketId = $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'field_visit',
            'subject' => 'Field visit needed',
            'customer_id' => $customer->id,
            'customer_location' => 'Kabul',
        ])->json('data.id');

        $this->postJson("/api/v1/tickets/{$ticketId}/assign", [
            'user_id' => $tech->id,
        ])->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_ASSIGNED)
            ->assertJsonPath('data.primary_assignee_id', $tech->id);

        $taskId = $this->postJson('/api/v1/tasks', [
            'branch_id' => $branch->id,
            'ticket_id' => $ticketId,
            'title' => 'Visit site',
            'type' => Task::TYPE_FIELD,
        ])->json('data.id');

        $this->postJson("/api/v1/tasks/{$taskId}/offer", [
            'user_id' => $tech->id,
        ])->assertOk()->assertJsonPath('data.status', Task::STATUS_OFFERED);

        $this->actingAsUser($tech);
        $this->postJson("/api/v1/tasks/{$taskId}/accept")->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_ACCEPTED);

        $this->postJson("/api/v1/tasks/{$taskId}/start")->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_IN_PROGRESS);

        $this->postJson("/api/v1/tasks/{$taskId}/complete")->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_COMPLETED);
    }

    public function test_task_reject_requires_reason(): void
    {
        $branch = $this->makeBranch(['code' => 'REJ']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        [$tech] = $this->makeCollectorUser($branch);
        $tech->givePermissionTo(['tasks.view', 'tasks.accept']);

        $this->actingAsUser($manager);
        $taskId = $this->postJson('/api/v1/tasks', [
            'branch_id' => $branch->id,
            'title' => 'Reject me',
            'type' => Task::TYPE_FIELD,
        ])->json('data.id');

        $this->postJson("/api/v1/tasks/{$taskId}/offer", ['user_id' => $tech->id])->assertOk();

        $this->actingAsUser($tech);
        $this->postJson("/api/v1/tasks/{$taskId}/reject", [])->assertStatus(422);

        $this->postJson("/api/v1/tasks/{$taskId}/reject", [
            'reason' => 'Already on another job',
        ])->assertOk()->assertJsonPath('data.status', Task::STATUS_REJECTED);
    }

    public function test_dependencies_block_start(): void
    {
        $branch = $this->makeBranch(['code' => 'DEP']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);

        /** @var TaskService $tasks */
        $tasks = app(TaskService::class);
        /** @var TaskAssignmentWorkflowService $workflow */
        $workflow = app(TaskAssignmentWorkflowService::class);

        $blocker = $tasks->create([
            'branch_id' => $branch->id,
            'title' => 'Blocker',
            'type' => Task::TYPE_OFFICE,
        ], $manager);

        $dependent = $tasks->create([
            'branch_id' => $branch->id,
            'title' => 'Dependent',
            'type' => Task::TYPE_OFFICE,
        ], $manager, [$blocker->id]);

        $this->expectException(\InvalidArgumentException::class);
        $workflow->start($dependent, $manager);
    }

    public function test_sla_pauses_on_waiting_customer(): void
    {
        $branch = $this->makeBranch(['code' => 'SLA']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        /** @var TicketService $tickets */
        $tickets = app(TicketService::class);
        $ticket = $tickets->create([
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'SLA pause',
            'customer_id' => $customer->id,
        ], $manager);

        $tickets->transition($ticket, Ticket::STATUS_TRIAGED, $manager);
        $tickets->transition($ticket->fresh(), Ticket::STATUS_IN_PROGRESS, $manager);

        $dueBefore = $ticket->fresh()->resolution_due_at->copy();

        $tickets->transition($ticket->fresh(), Ticket::STATUS_WAITING_CUSTOMER, $manager);
        $state = TicketSlaState::query()->where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($state->paused_at);
        $this->assertSame('paused', $state->response_state);

        $this->travel(10)->minutes();

        $tickets->transition($ticket->fresh(), Ticket::STATUS_IN_PROGRESS, $manager);
        $ticket = $ticket->fresh();
        $state = $state->fresh();

        $this->assertNull($state->paused_at);
        $this->assertTrue($ticket->resolution_due_at->gt($dueBefore));
    }

    public function test_whatsapp_intake_idempotency(): void
    {
        $branch = $this->makeBranch(['code' => 'WAI']);
        $this->seed(Stage7OrgSeeder::class);

        $conversation = WhatsAppConversation::query()->create([
            'branch_id' => $branch->id,
            'phone_e164' => '+93700000001',
            'status' => 'open',
        ]);

        $inbound = WhatsAppInboundMessage::query()->create([
            'conversation_id' => $conversation->id,
            'meta_message_id' => 'wamid.idempotent-1',
            'from_phone' => '+93700000001',
            'type' => 'text',
            'body' => 'Need help with internet',
            'received_at' => now(),
        ]);

        /** @var TicketIntakeService $intake */
        $intake = app(TicketIntakeService::class);
        $first = $intake->handleInboundMessage($inbound->id);
        $second = $intake->handleInboundMessage($inbound->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TicketIntakeSuggestion::query()->count());
    }

    public function test_installation_queue_create_and_status(): void
    {
        $branch = $this->makeBranch(['code' => 'INS']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);

        $this->actingAsUser($manager);
        $id = $this->postJson('/api/v1/installations', [
            'branch_id' => $branch->id,
            'prospect_name' => 'Ahmad',
            'phone' => '+93701112233',
            'address' => 'Kabul',
        ])->assertCreated()
            ->assertJsonPath('data.status', Installation::STATUS_REQUEST_RECEIVED)
            ->json('data.id');

        $this->postJson("/api/v1/installations/{$id}/transition", [
            'status' => Installation::STATUS_CUSTOMER_VERIFICATION,
        ])->assertOk()
            ->assertJsonPath('data.status', Installation::STATUS_CUSTOMER_VERIFICATION);
    }

    public function test_permission_denial(): void
    {
        $branch = $this->makeBranch(['code' => 'DEN']);
        $this->seed(Stage7OrgSeeder::class);
        [$collectorUser] = $this->makeCollectorUser($branch);

        $this->actingAsUser($collectorUser);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'internal_request',
            'subject' => 'Should fail',
            'description' => 'no permission',
        ])->assertForbidden();
    }

    public function test_audit_log_created_on_ticket(): void
    {
        $branch = $this->makeBranch(['code' => 'AUD']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAsUser($manager);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Audit me',
            'customer_id' => $customer->id,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.created',
            'branch_id' => $branch->id,
        ]);
        $this->assertTrue(AuditLog::query()->where('action', 'ticket.created')->exists());
    }

    public function test_search_isolation(): void
    {
        $branchA = $this->makeBranch(['code' => 'SEA']);
        $branchB = $this->makeBranch(['code' => 'SEB']);
        $this->seed(Stage7OrgSeeder::class);
        $managerA = $this->makeManager($branchA);
        $managerB = $this->makeManager($branchB);
        $customer = $this->makeCustomer($branchA);

        $this->actingAsUser($managerA);
        $this->postJson('/api/v1/tickets', [
            'branch_id' => $branchA->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'UniqueSearchNeedleXYZ',
            'customer_id' => $customer->id,
        ])->assertCreated();

        $this->actingAsUser($managerB);
        $this->getJson('/api/v1/operations/search?q=UniqueSearchNeedleXYZ')
            ->assertOk()
            ->assertJsonPath('data.tickets', []);

        $this->actingAsUser($managerA);
        $this->getJson('/api/v1/operations/search?q=UniqueSearchNeedleXYZ')
            ->assertOk();
        $this->assertNotEmpty($this->getJson('/api/v1/operations/search?q=UniqueSearchNeedleXYZ')->json('data.tickets'));
    }

    public function test_reopen_workflow(): void
    {
        $branch = $this->makeBranch(['code' => 'ROP']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        /** @var TicketService $tickets */
        $tickets = app(TicketService::class);
        $ticket = $tickets->create([
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Reopen me',
            'customer_id' => $customer->id,
        ], $manager);

        $tickets->transition($ticket, Ticket::STATUS_TRIAGED, $manager);
        $tickets->transition($ticket->fresh(), Ticket::STATUS_IN_PROGRESS, $manager);
        $tickets->resolve($ticket->fresh(), $manager, 'Fixed');
        $tickets->close($ticket->fresh(), $manager, 'Done');

        $this->actingAsUser($manager);
        $this->postJson("/api/v1/tickets/{$ticket->id}/reopen", [
            'reason' => 'Customer called back',
        ])->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_REOPENED)
            ->assertJsonPath('data.reopened_count', 1);
    }
}
