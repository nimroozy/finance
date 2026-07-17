<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketIntakeSuggestion;
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

        $metaId = $inbound->meta_message_id;

        $existing = TicketIntakeSuggestion::query()
            ->where(function ($q) use ($inbound, $metaId) {
                $q->where('inbound_message_id', $inbound->id);
                if ($metaId) {
                    $q->orWhere('meta_message_id', $metaId);
                }
            })
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
                ->where('conversation_id', $conversationId)
                ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
                ->orderByDesc('id')
                ->first();
        }

        $subject = $this->subjectFromBody($inbound->body);

        if ($openTicket) {
            $meta = $openTicket->meta ?? [];
            $meta['inbound_message_ids'] = array_values(array_unique(array_merge(
                $meta['inbound_message_ids'] ?? [],
                [$inbound->id],
            )));
            $openTicket->meta = $meta;
            $openTicket->conversation_id ??= $conversationId;
            $openTicket->save();

            $suggestion = TicketIntakeSuggestion::query()->create([
                'branch_id' => $branchId ?? $openTicket->branch_id,
                'inbound_message_id' => $inbound->id,
                'meta_message_id' => $metaId,
                'conversation_id' => $conversationId,
                'customer_id' => $customerId ?? $openTicket->customer_id,
                'ticket_id' => $openTicket->id,
                'status' => TicketIntakeSuggestion::STATUS_ACCEPTED,
                'suggested_type' => Ticket::TYPE_WHATSAPP,
                'suggested_category' => config('ticketing.intake.default_category', 'general'),
                'suggested_priority' => config('ticketing.intake.default_priority', 'normal'),
                'subject' => $subject,
                'body' => $inbound->body,
                'payload' => ['linked_existing_ticket' => true],
            ]);

            $this->audit->log('ticket.intake_linked', $openTicket, null, [
                'inbound_message_id' => $inbound->id,
                'suggestion_id' => $suggestion->id,
            ], $openTicket->branch_id);

            return $suggestion;
        }

        $suggestion = TicketIntakeSuggestion::query()->create([
            'branch_id' => $branchId,
            'inbound_message_id' => $inbound->id,
            'meta_message_id' => $metaId,
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'ticket_id' => null,
            'status' => TicketIntakeSuggestion::STATUS_PENDING,
            'suggested_type' => Ticket::TYPE_WHATSAPP,
            'suggested_category' => config('ticketing.intake.default_category', 'general'),
            'suggested_priority' => config('ticketing.intake.default_priority', 'normal'),
            'subject' => $subject,
            'body' => $inbound->body,
            'payload' => ['from_phone' => $inbound->from_phone],
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
            $ticket = $this->tickets->create([
                'branch_id' => $suggestion->branch_id,
                'type' => $suggestion->suggested_type ?: Ticket::TYPE_WHATSAPP,
                'channel' => 'whatsapp',
                'priority' => $suggestion->suggested_priority ?: 'normal',
                'category' => $suggestion->suggested_category,
                'subject' => $suggestion->subject ?: 'WhatsApp inbound',
                'description' => $suggestion->body,
                'customer_id' => $suggestion->customer_id,
                'conversation_id' => $suggestion->conversation_id,
                'reporter_id' => $actor?->id,
                'meta' => [
                    'intake_suggestion_id' => $suggestion->id,
                    'inbound_message_id' => $suggestion->inbound_message_id,
                    'meta_message_id' => $suggestion->meta_message_id,
                ],
            ], $actor);

            $suggestion->ticket_id = $ticket->id;
            $suggestion->status = $auto
                ? TicketIntakeSuggestion::STATUS_AUTO_CREATED
                : TicketIntakeSuggestion::STATUS_ACCEPTED;
            $suggestion->save();

            $this->audit->log('ticket.intake_created', $ticket, null, [
                'suggestion_id' => $suggestion->id,
                'auto' => $auto,
            ], $ticket->branch_id);

            return $suggestion->fresh();
        });
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
