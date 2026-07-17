<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tickets\Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'source' => $this->source,
            'type_code' => $this->type_code,
            'category' => $this->category,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority,
            'severity' => $this->severity,
            'status' => $this->status,
            'primary_assignee_id' => $this->primary_assignee_id,
            'assigned_department_id' => $this->assigned_department_id,
            'assigned_team_id' => $this->assigned_team_id,
            'response_due_at' => optional($this->response_due_at)->toIso8601String(),
            'resolution_due_at' => optional($this->resolution_due_at)->toIso8601String(),
            'first_response_at' => optional($this->first_response_at)->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'closed_at' => optional($this->closed_at)->toIso8601String(),
            'reopened_count' => $this->reopened_count,
            'resolution_summary' => $this->resolution_summary,
            'customer_confirmation' => $this->customer_confirmation,
            'whatsapp_conversation_id' => $this->whatsapp_conversation_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'sla_state' => $this->whenLoaded('slaState'),
            'watchers' => $this->whenLoaded('watchers'),
            'primary_assignee' => $this->whenLoaded('primaryAssignee'),
        ];
    }
}
