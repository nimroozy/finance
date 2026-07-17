<?php

namespace App\Services\Crm;

use App\Events\Crm\SurveyCompleted;
use App\Models\Crm\Lead;
use App\Models\Crm\SiteSurvey;
use App\Models\Tickets\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SiteSurveyService
{
    public function __construct(
        private TaskService $tasks,
        private LeadPipelineService $pipeline,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Lead $lead, array $data, ?User $actor = null): SiteSurvey
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $task = $this->tasks->create([
                'branch_id' => $lead->branch_id,
                'title' => 'Site survey for lead '.$lead->lead_number,
                'description' => sprintf(
                    "CRM site survey task.\nlead_id=%d\nlead_number=%s\ncontact=%s\nphone=%s\naddress=%s",
                    $lead->id,
                    $lead->lead_number,
                    $lead->contact_person ?? '',
                    $lead->phone ?? '',
                    $lead->address ?? '',
                ),
                'type' => Task::TYPE_FIELD,
                'priority' => Task::PRIORITY_NORMAL,
                'assignee_id' => $data['technician_id'] ?? null,
                'location' => $lead->address,
                'gps_lat' => $data['gps_lat'] ?? $lead->gps_lat,
                'gps_lng' => $data['gps_lng'] ?? $lead->gps_lng,
                'due_at' => $data['survey_date'] ?? null,
            ], $actor);

            $survey = SiteSurvey::query()->create([
                'lead_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'gps_lat' => $data['gps_lat'] ?? $lead->gps_lat,
                'gps_lng' => $data['gps_lng'] ?? $lead->gps_lng,
                'los_status' => $data['los_status'] ?? null,
                'signal_estimate' => $data['signal_estimate'] ?? null,
                'mounting_location' => $data['mounting_location'] ?? null,
                'power_availability' => $data['power_availability'] ?? null,
                'equipment_recommendation' => $data['equipment_recommendation'] ?? null,
                'technician_id' => $data['technician_id'] ?? null,
                'survey_date' => $data['survey_date'] ?? null,
                'customer_approved' => $data['customer_approved'] ?? null,
                'notes' => $data['notes'] ?? null,
                'task_id' => $task->id,
            ]);

            if ($lead->canTransitionTo(Lead::STAGE_SITE_SURVEY)) {
                $this->pipeline->transition($lead, Lead::STAGE_SITE_SURVEY, $actor, 'site_survey_created');
            }

            $this->audit->log('crm.site_survey.created', $survey, null, array_merge(
                $survey->toArray(),
                ['task_id' => $task->id],
            ), $lead->branch_id);

            return $survey->fresh('task');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(SiteSurvey $survey, array $data = [], ?User $actor = null): SiteSurvey
    {
        return DB::transaction(function () use ($survey, $data, $actor) {
            $fields = [
                'gps_lat', 'gps_lng', 'los_status', 'signal_estimate', 'mounting_location',
                'power_availability', 'equipment_recommendation', 'technician_id',
                'survey_date', 'customer_approved', 'notes',
            ];
            $old = $survey->only($fields);
            $survey->fill(collect($data)->only($fields)->all());
            if (empty($survey->survey_date)) {
                $survey->survey_date = now()->toDateString();
            }
            $survey->save();

            $lead = $survey->lead;
            if ($lead && $lead->canTransitionTo(Lead::STAGE_QUOTATION)) {
                $this->pipeline->transition($lead, Lead::STAGE_QUOTATION, $actor, 'site_survey_completed');
            }

            $this->audit->log('crm.site_survey.completed', $survey, $old, $survey->only($fields), $survey->branch_id);
            SurveyCompleted::dispatch($survey->id, (int) $survey->branch_id, (int) $survey->lead_id);

            return $survey->fresh('task');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SiteSurvey $survey, array $data, ?User $actor = null): SiteSurvey
    {
        $fields = [
            'gps_lat', 'gps_lng', 'los_status', 'signal_estimate', 'mounting_location',
            'power_availability', 'equipment_recommendation', 'technician_id',
            'survey_date', 'customer_approved', 'notes',
        ];
        $old = $survey->only($fields);
        $survey->fill(collect($data)->only($fields)->all());
        $survey->save();
        $this->audit->log('crm.site_survey.updated', $survey, $old, $survey->only($fields), $survey->branch_id);

        return $survey->fresh();
    }
}
