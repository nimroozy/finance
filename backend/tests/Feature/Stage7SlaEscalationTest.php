<?php

namespace Tests\Feature;

use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketSlaState;
use App\Services\Sla\EscalationService;
use App\Services\Sla\SlaClockService;
use App\Services\Tickets\TicketService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7EscalationRuleSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7SlaPolicySeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage7SlaEscalationTest extends TestCase
{
    use CreatesCollectionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(Stage7SlaPolicySeeder::class);
        $this->seed(Stage7TicketTypeSeeder::class);
        $this->seed(Stage7EscalationRuleSeeder::class);
    }

    public function test_sla_clock_sets_due_dates_and_state(): void
    {
        $branch = $this->makeBranch(['code' => 'SLC']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $ticket = app(TicketService::class)->create([
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'SLA clock',
            'customer_id' => $customer->id,
            'priority' => Ticket::PRIORITY_HIGH,
        ], $manager);

        $this->assertNotNull($ticket->response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
        $this->assertDatabaseHas('ticket_sla_states', [
            'ticket_id' => $ticket->id,
            'response_state' => 'running',
            'resolution_state' => 'running',
        ]);
    }

    public function test_evaluate_breaches_records_event(): void
    {
        $branch = $this->makeBranch(['code' => 'BRC']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $ticket = app(TicketService::class)->create([
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Breach me',
            'customer_id' => $customer->id,
        ], $manager);

        $ticket->response_due_at = now()->subHour();
        $ticket->resolution_due_at = now()->subMinutes(30);
        $ticket->save();

        $breached = app(SlaClockService::class)->evaluateBreaches(10);
        $this->assertNotEmpty($breached);
        $this->assertDatabaseHas('sla_breach_events', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_escalation_evaluate_creates_record(): void
    {
        $branch = $this->makeBranch(['code' => 'ESC']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer($branch);

        $ticket = app(TicketService::class)->create([
            'branch_id' => $branch->id,
            'type_code' => 'billing_inquiry',
            'subject' => 'Escalate me',
            'customer_id' => $customer->id,
        ], $manager);

        $ticket->resolution_due_at = now()->subHours(2);
        $ticket->save();

        TicketSlaState::query()->where('ticket_id', $ticket->id)->update(['paused_at' => null]);

        $created = app(EscalationService::class)->evaluate(10);
        $this->assertNotEmpty($created);
        $this->assertDatabaseHas('escalations', [
            'ticket_id' => $ticket->id,
            'status' => 'open',
        ]);
    }
}
