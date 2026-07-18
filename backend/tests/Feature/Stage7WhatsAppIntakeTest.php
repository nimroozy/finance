<?php

namespace Tests\Feature;

use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketIntakeSuggestion;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppInboundMessage;
use App\Services\Tickets\TicketIntakeService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\Stage7OrgSeeder;
use Database\Seeders\Stage7SlaPolicySeeder;
use Database\Seeders\Stage7TicketTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCollectionFixtures;
use Tests\TestCase;

class Stage7WhatsAppIntakeTest extends TestCase
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

    public function test_intake_creates_suggestion_then_ticket(): void
    {
        $branch = $this->makeBranch(['code' => 'WAT']);
        $this->seed(Stage7OrgSeeder::class);
        $manager = $this->makeManager($branch);

        $conversation = WhatsAppConversation::query()->create([
            'branch_id' => $branch->id,
            'phone_e164' => '+93701234567',
            'status' => 'open',
        ]);

        $inbound = WhatsAppInboundMessage::query()->create([
            'conversation_id' => $conversation->id,
            'meta_message_id' => 'wamid.create-ticket-1',
            'from_phone' => '+93701234567',
            'type' => 'text',
            'body' => 'Internet is down',
            'received_at' => now(),
        ]);

        $suggestion = app(TicketIntakeService::class)->handleInboundMessage($inbound->id);
        $this->assertSame(TicketIntakeSuggestion::STATUS_PENDING, $suggestion->status);

        $this->actingAsUser($manager);
        $this->postJson("/api/v1/ticket-intake/{$suggestion->id}/create-ticket")
            ->assertOk()
            ->assertJsonPath('data.suggestion.status', TicketIntakeSuggestion::STATUS_TICKET_CREATED);

        $this->assertDatabaseHas('tickets', [
            'id' => $suggestion->fresh()->ticket_id,
            'source' => Ticket::SOURCE_WHATSAPP,
            'type_code' => 'whatsapp_general',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_duplicate_inbound_is_idempotent(): void
    {
        $branch = $this->makeBranch(['code' => 'WAD']);
        $this->seed(Stage7OrgSeeder::class);

        $conversation = WhatsAppConversation::query()->create([
            'branch_id' => $branch->id,
            'phone_e164' => '+93709876543',
            'status' => 'open',
        ]);

        $inbound = WhatsAppInboundMessage::query()->create([
            'conversation_id' => $conversation->id,
            'meta_message_id' => 'wamid.dup-1',
            'from_phone' => '+93709876543',
            'type' => 'text',
            'body' => 'Hello',
            'received_at' => now(),
        ]);

        $intake = app(TicketIntakeService::class);
        $a = $intake->handleInboundMessage($inbound->id);
        $b = $intake->handleInboundMessage($inbound->id);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, TicketIntakeSuggestion::query()->count());
    }
}
