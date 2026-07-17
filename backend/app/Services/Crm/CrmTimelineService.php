<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\CoverageCheck;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Crm\LeadStageTransition;
use App\Models\Crm\Quotation;
use App\Models\Crm\SiteSurvey;
use App\Models\User;

class CrmTimelineService
{
    /**
     * @return list<array{at: string, type: string, title: string, summary: ?string, meta: array}>
     */
    public function forLead(Lead $lead, ?User $viewer = null, int $limit = 100): array
    {
        $events = collect();

        $events->push([
            'at' => optional($lead->created_at)->toIso8601String(),
            'type' => 'lead_created',
            'title' => $lead->lead_number,
            'summary' => 'Lead created',
            'meta' => ['lead_id' => $lead->id, 'stage' => $lead->pipeline_stage],
        ]);

        LeadStageTransition::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (LeadStageTransition $t) use ($events) {
                $events->push([
                    'at' => optional($t->created_at)->toIso8601String(),
                    'type' => 'stage_change',
                    'title' => ($t->from_stage ?? '—').' → '.$t->to_stage,
                    'summary' => $t->reason ?: $t->notes,
                    'meta' => [
                        'from' => $t->from_stage,
                        'to' => $t->to_stage,
                        'user_id' => $t->user_id,
                    ],
                ]);
            });

        Activity::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (Activity $a) use ($events) {
                $events->push([
                    'at' => optional($a->performed_at ?? $a->created_at)->toIso8601String(),
                    'type' => 'activity',
                    'title' => $a->subject ?: $a->type,
                    'summary' => $a->body,
                    'meta' => ['activity_id' => $a->id, 'activity_type' => $a->type],
                ]);
            });

        FollowUp::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (FollowUp $f) use ($events) {
                $events->push([
                    'at' => optional($f->due_at ?? $f->created_at)->toIso8601String(),
                    'type' => 'follow_up',
                    'title' => 'Follow-up '.$f->status,
                    'summary' => $f->notes,
                    'meta' => ['follow_up_id' => $f->id, 'status' => $f->status],
                ]);
            });

        CoverageCheck::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (CoverageCheck $c) use ($events) {
                $events->push([
                    'at' => optional($c->checked_at ?? $c->created_at)->toIso8601String(),
                    'type' => 'coverage_check',
                    'title' => 'Coverage: '.$c->result,
                    'summary' => $c->notes,
                    'meta' => ['coverage_check_id' => $c->id, 'result' => $c->result],
                ]);
            });

        SiteSurvey::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (SiteSurvey $s) use ($events) {
                $events->push([
                    'at' => optional($s->created_at)->toIso8601String(),
                    'type' => 'site_survey',
                    'title' => 'Site survey',
                    'summary' => $s->notes,
                    'meta' => ['site_survey_id' => $s->id, 'task_id' => $s->task_id],
                ]);
            });

        Quotation::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->each(function (Quotation $q) use ($events) {
                $events->push([
                    'at' => optional($q->created_at)->toIso8601String(),
                    'type' => 'quotation',
                    'title' => $q->quote_number,
                    'summary' => 'Status: '.$q->status,
                    'meta' => ['quotation_id' => $q->id, 'status' => $q->status],
                ]);
            });

        return $events
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }
}
