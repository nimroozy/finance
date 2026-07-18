<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tickets\Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'team_id' => $this->team_id,
            'assignee_id' => $this->assignee_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'ticket_id' => $this->ticket_id,
            'installation_id' => $this->installation_id,
            'customer_id' => $this->customer_id,
            'due_at' => optional($this->due_at)->toIso8601String(),
            'started_at' => optional($this->started_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'location' => $this->location,
            'checklist' => $this->checklist,
            'requires_approval' => $this->requires_approval,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee'),
            'depends_on_tasks' => $this->whenLoaded('dependsOnTasks'),
        ];
    }
}
