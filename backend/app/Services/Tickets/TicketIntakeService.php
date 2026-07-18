<?php

namespace App\Services\Tickets;

use App\Models\Tickets\Ticket;
use App\Models\Tickets\TicketIntakeSuggestion;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppInboundMessage;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketIntakeService
{
    public function __construct(
        private TicketService $tickets,
        private AuditLogger $audit,
    ) {}

    public function handleInboundMessage(int $inboundMessageId, bool $autoCreate = false): ?TicketIntakeSuggestion
    {
        $inbound = WhatsAppInboundMessage::query()->find($inboundMessageId);
        if (! $inbound) {
            return null;
        }

        $existing = TicketIntakeSuggestion::query()
            ->where('whatsapp_inbound_message_id', $inbound->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversationId = $inbound->conversation_id;
        $customerId = null;
        $branchId = null;

        if ($conversationId) {
            $conversation = DB::table('whatsapp_conversations')->where('id', $conversationId)->first();
            $customerId = $conversation->customer_id ?? null;
            $branchId = $conversation->branch_id ?? null;
        }

        $openTicket = null;
        if ($customerId) {
            $openTicket = Ticket::query()
                ->where('customer_id', $customerId)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->first();
        } elseif ($conversationId) {
            $openTicket = Ticket::query()
                ->where('whatsapp_conversation_id', $conversationId)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->first();
        }

        $subject = $this->subjectFromBody($inbound->body ?? null);

        if ($openTicket) {
            $meta = $openTicket->tags ?? [];
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['inbound_message_ids'] = array_values(array_unique(array_merge(
                $meta['inbound_message_ids'] ?? [],
                [$inbound->id],
            )));
            $openTicket->tags = $meta;
            $openTicket->whatsapp_conversation_id ??= $conversationId;
            $openTicket->save();

            $suggestion = TicketIntakeSuggestion::query()->create([
                'whatsapp_inbound_message_id' => $inbound->id,
                'conversation_id' => $conversationId,
                'branch_id' => $branchId ?? $openTicket->branch_id,
                'customer_id' => $customerId ?? $openTicket->customer_id,
                'status' => TicketIntakeSuggestion::STATUS_APPENDED,
                'ticket_id' => $openTicket->id,
                'meta' => [
                    'subject' => $subject,
                    'body' => $inbound->body ?? null,
                    'linked_existing_ticket' => true,
                    'from_phone' => $inbound->from_phone ?? null,
                ],
            ]);

            $this->audit->log('ticket.intake_linked', $openTicket, null, [
                'whatsapp_inbound_message_id' => $inbound->id,
                'suggestion_id' => $suggestion->id,
            ], $openTicket->branch_id);

            return $suggestion;
        }

        $suggestion = TicketIntakeSuggestion::query()->create([
            'whatsapp_inbound_message_id' => $inbound->id,
            'conversation_id' => $conversationId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'status' => TicketIntakeSuggestion::STATUS_PENDING,
            'ticket_id' => null,
            'meta' => [
                'subject' => $subject,
                'body' => $inbound->body ?? null,
                'suggested_type_code' => 'whatsapp_general',
                'suggested_priority' => config('ticketing.intake.default_priority', 'normal'),
                'suggested_category' => config('ticketing.intake.default_category', 'general'),
                'from_phone' => $inbound->from_phone ?? null,
            ],
        ]);

        $shouldAuto = $autoCreate || (bool) config('ticketing.intake.auto_create', false);
        if ($shouldAuto && $branchId) {
            return $this->createTicketFromSuggestion($suggestion, null, true);
        }

        return $suggestion;
    }

    public function createTicketFromSuggestion(
        TicketIntakeSuggestion $suggestion,
        ?User $actor = null,
        bool $auto = false,
    ): TicketIntakeSuggestion {
        if ($suggestion->ticket_id) {
            return $suggestion;
        }

        if (! $suggestion->branch_id) {
            throw new InvalidArgumentException('Cannot create ticket without branch_id on intake suggestion.');
        }

        return DB::transaction(function () use ($suggestion, $actor, $auto) {
            $meta = $suggestion->meta ?? [];

            $ticket = $this->tickets->create([
                'branch_id' => $suggestion->branch_id,
                'type_code' => $meta['suggested_type_code'] ?? 'whatsapp_general',
                'source' => Ticket::SOURCE_WHATSAPP,
                'priority' => $meta['suggested_priority'] ?? 'normal',
                'category' => $meta['suggested_category'] ?? null,
                'subject' => $meta['subject'] ?? 'WhatsApp inbound',
                'description' => $meta['body'] ?? null,
                'customer_id' => $suggestion->customer_id,
                'whatsapp_conversation_id' => $suggestion->conversation_id,
                'customer_phone' => $meta['from_phone'] ?? null,
                'external_reference' => 'intake:'.$suggestion->id,
            ], $actor);

            $suggestion->ticket_id = $ticket->id;
            $suggestion->status = TicketIntakeSuggestion::STATUS_TICKET_CREATED;
            $suggestion->meta = array_merge($meta, ['auto' => $auto]);
            $suggestion->save();

            $this->audit->log('ticket.intake_created', $ticket, null, [
                'suggestion_id' => $suggestion->id,
                'auto' => $auto,
            ], $ticket->branch_id);

            return $suggestion->fresh();
        });
    }

    public function dismiss(TicketIntakeSuggestion $suggestion, ?User $actor = null): TicketIntakeSuggestion
    {
        $suggestion->status = TicketIntakeSuggestion::STATUS_DISMISSED;
        $suggestion->save();

        $this->audit->log('ticket.intake_dismissed', $suggestion, null, [
            'actor_id' => $actor?->id,
        ], $suggestion->branch_id);

        return $suggestion->fresh();
    }

    private function subjectFromBody(?string $body): string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return 'WhatsApp inbound message';
        }

        return mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'...' : $body;
    }
}
