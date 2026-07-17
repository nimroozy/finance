<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Inventory\CustomerEquipment;
use App\Models\Services\Service;
use App\Models\Services\ServiceActivation;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceFinanceHold;
use App\Models\Services\ServiceRelocation;
use App\Models\Services\ServiceRenewal;
use App\Models\Services\ServiceStatusTransition;
use App\Models\Services\ServiceSuspension;
use App\Models\Tickets\Installation;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Collection;

class ServiceTimelineService
{
    /**
     * @return list<array{id: string, type: string, occurred_at: ?string, actor: ?array, title: string, summary: ?string, meta: array}>
     */
    public function forService(Service $service): array
    {
        $events = collect();

        ServiceStatusTransition::query()->where('service_id', $service->id)->get()->each(function ($t) use ($events) {
            $events->push($this->dto(
                id: 'transition-'.$t->id,
                type: 'status_transition',
                occurredAt: $t->created_at,
                actorId: $t->user_id,
                title: trim(($t->from_commercial ?? $t->from_operational ?? '—').' → '.($t->to_commercial ?? $t->to_operational ?? '—')),
                summary: $t->reason ?: $t->comment,
                meta: $t->toArray(),
            ));
        });

        ServiceActivation::query()->where('service_id', $service->id)->get()->each(function ($a) use ($events) {
            $events->push($this->dto(
                id: 'activation-'.$a->id,
                type: 'activation',
                occurredAt: $a->activated_at,
                actorId: $a->activated_by,
                title: 'Service activated',
                summary: $a->notes,
                meta: $a->toArray(),
            ));
        });

        ServiceSuspension::query()->where('service_id', $service->id)->get()->each(function ($s) use ($events) {
            $events->push($this->dto(
                id: 'suspension-'.$s->id,
                type: 'suspension',
                occurredAt: $s->effective_at,
                actorId: $s->requested_by ?? $s->approved_by,
                title: 'Service suspended',
                summary: $s->reason,
                meta: $s->toArray(),
            ));
        });

        ServiceCancellation::query()->where('service_id', $service->id)->get()->each(function ($c) use ($events) {
            $events->push($this->dto(
                id: 'cancellation-'.$c->id,
                type: 'cancellation',
                occurredAt: $c->cancelled_at ?? $c->created_at,
                actorId: $c->requested_by,
                title: 'Cancellation '.$c->status,
                summary: $c->reason,
                meta: $c->toArray(),
            ));
        });

        ServiceChangeRequest::query()->where('service_id', $service->id)->get()->each(function ($c) use ($events) {
            $events->push($this->dto(
                id: 'change-'.$c->id,
                type: 'change_request',
                occurredAt: $c->created_at,
                actorId: $c->requested_by,
                title: 'Change request '.$c->status,
                summary: $c->reason,
                meta: $c->toArray(),
            ));
        });

        ServiceRelocation::query()->where('service_id', $service->id)->get()->each(function ($r) use ($events) {
            $events->push($this->dto(
                id: 'relocation-'.$r->id,
                type: 'relocation',
                occurredAt: $r->created_at,
                actorId: null,
                title: 'Relocation '.$r->status,
                summary: $r->notes,
                meta: $r->toArray(),
            ));
        });

        ServiceRenewal::query()->where('service_id', $service->id)->get()->each(function ($r) use ($events) {
            $events->push($this->dto(
                id: 'renewal-'.$r->id,
                type: 'renewal',
                occurredAt: $r->created_at,
                actorId: null,
                title: 'Renewal',
                summary: $r->notes ?? null,
                meta: $r->toArray(),
            ));
        });

        ServiceFinanceHold::query()->where('service_id', $service->id)->get()->each(function ($h) use ($events) {
            $events->push($this->dto(
                id: 'hold-'.$h->id,
                type: 'finance_hold',
                occurredAt: $h->effective_at,
                actorId: $h->approved_by,
                title: 'Finance hold '.$h->status,
                summary: $h->reason,
                meta: $h->toArray(),
            ));
        });

        // CRM / install
        if ($service->installation_id) {
            $installation = Installation::query()->find($service->installation_id);
            if ($installation) {
                $events->push($this->dto(
                    id: 'install-'.$installation->id,
                    type: 'installation',
                    occurredAt: $installation->updated_at ?? $installation->created_at,
                    actorId: null,
                    title: 'Installation '.$installation->status,
                    summary: $installation->address ?? $installation->prospect_name,
                    meta: ['installation_id' => $installation->id, 'status' => $installation->status],
                ));
            }
        }

        // Inventory / equipment
        CustomerEquipment::withoutGlobalScopes()
            ->where('service_id', $service->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->each(function (CustomerEquipment $eq) use ($events) {
                $events->push($this->dto(
                    id: 'equipment-'.$eq->id,
                    type: 'inventory',
                    occurredAt: $eq->installed_at ?? $eq->created_at,
                    actorId: null,
                    title: 'Equipment '.$eq->status,
                    summary: $eq->serial_number,
                    meta: ['equipment_id' => $eq->id, 'status' => $eq->status, 'serial_number' => $eq->serial_number],
                ));
            });

        // Tickets / tasks
        Ticket::query()->where('service_id', $service->id)->orderByDesc('id')->limit(50)->get()
            ->each(function (Ticket $ticket) use ($events) {
                $events->push($this->dto(
                    id: 'ticket-'.$ticket->id,
                    type: 'ticket',
                    occurredAt: $ticket->created_at,
                    actorId: $ticket->created_by ?? null,
                    title: 'Ticket '.$ticket->ticket_number,
                    summary: $ticket->subject ?? $ticket->status,
                    meta: ['ticket_id' => $ticket->id, 'status' => $ticket->status],
                ));
            });

        if (class_exists(Task::class) && \Illuminate\Support\Facades\Schema::hasColumn((new Task)->getTable(), 'service_id')) {
            Task::query()
                ->where('service_id', $service->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->each(function (Task $task) use ($events) {
                    $events->push($this->dto(
                        id: 'task-'.$task->id,
                        type: 'task',
                        occurredAt: $task->created_at,
                        actorId: $task->created_by ?? $task->assigned_to,
                        title: 'Task: '.($task->title ?? $task->id),
                        summary: $task->status,
                        meta: ['task_id' => $task->id, 'status' => $task->status],
                    ));
                });
        }

        // Zoho sync markers on service
        if ($service->zoho_customer_id) {
            $events->push($this->dto(
                id: 'zoho-link-'.$service->id,
                type: 'zoho',
                occurredAt: $service->created_at,
                actorId: null,
                title: 'Zoho customer linked',
                summary: (string) $service->zoho_customer_id,
                meta: ['zoho_customer_id' => $service->zoho_customer_id],
            ));
        }

        // WhatsApp (via tickets on this service)
        $conversationIds = Ticket::query()
            ->where('service_id', $service->id)
            ->whereNotNull('whatsapp_conversation_id')
            ->pluck('whatsapp_conversation_id')
            ->filter()
            ->unique()
            ->values();

        if ($conversationIds->isNotEmpty() && class_exists(WhatsAppMessage::class) && \Illuminate\Support\Facades\Schema::hasTable('whatsapp_messages')) {
            WhatsAppMessage::query()
                ->whereIn('whatsapp_conversation_id', $conversationIds)
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->each(function ($msg) use ($events) {
                    $events->push($this->dto(
                        id: 'whatsapp-'.$msg->id,
                        type: 'whatsapp',
                        occurredAt: $msg->created_at ?? $msg->sent_at ?? null,
                        actorId: null,
                        title: 'WhatsApp message',
                        summary: is_string($msg->body ?? null) ? mb_substr($msg->body, 0, 120) : null,
                        meta: ['message_id' => $msg->id, 'direction' => $msg->direction ?? null],
                    ));
                });
        }

        return $events
            ->sortByDesc(fn ($e) => $e['occurred_at'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{id: string, type: string, occurred_at: ?string, actor: ?array{id: int, name: ?string}, title: string, summary: ?string, meta: array}
     */
    private function dto(string $id, string $type, $occurredAt, ?int $actorId, string $title, ?string $summary, array $meta): array
    {
        $actor = null;
        if ($actorId) {
            $user = User::query()->find($actorId);
            $actor = $user ? ['id' => $user->id, 'name' => $user->name] : ['id' => $actorId, 'name' => null];
        }

        return [
            'id' => $id,
            'type' => $type,
            'occurred_at' => $occurredAt ? (is_string($occurredAt) ? $occurredAt : $occurredAt->toIso8601String()) : null,
            'actor' => $actor,
            'title' => $title,
            'summary' => $summary,
            'meta' => $meta,
        ];
    }
}
