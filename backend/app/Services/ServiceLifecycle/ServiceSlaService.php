<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceSlaTemplate;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Services\AuditLogger;

class ServiceSlaService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Inherit SLA template defaults onto a ticket linked to a service.
     */
    public function applyToTicket(Ticket $ticket, Service $service, ?User $actor = null): Ticket
    {
        if (! $ticket->service_id) {
            $ticket->service_id = $service->id;
        }

        $template = $service->slaTemplate
            ?? ($service->sla_template_id ? ServiceSlaTemplate::query()->find($service->sla_template_id) : null);

        if ($template) {
            if (! $ticket->response_due_at && $template->response_minutes) {
                $ticket->response_due_at = now()->addMinutes((int) $template->response_minutes);
            }
            if (! $ticket->resolution_due_at && $template->resolution_minutes) {
                $ticket->resolution_due_at = now()->addMinutes((int) $template->resolution_minutes);
            }
        }

        $ticket->save();
        $this->audit->log('service.sla_applied_to_ticket', $service, null, [
            'ticket_id' => $ticket->id,
            'sla_template_id' => $template?->id,
        ], $service->branch_id);

        return $ticket->fresh();
    }
}
