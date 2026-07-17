<?php

namespace App\Services\Crm;

use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Crm\Quotation;
use App\Models\Crm\SalesTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrmDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $branchId, ?User $viewer = null): array
    {
        $leads = Lead::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byStage = (clone $leads)
            ->select('pipeline_stage', DB::raw('count(*) as total'))
            ->groupBy('pipeline_stage')
            ->pluck('total', 'pipeline_stage')
            ->all();

        $openFollowUps = FollowUp::query()
            ->whereIn('status', [FollowUp::STATUS_PENDING, FollowUp::STATUS_OVERDUE])
            ->when($branchId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('branch_id', $branchId)))
            ->count();

        $overdueFollowUps = FollowUp::query()
            ->where('status', FollowUp::STATUS_OVERDUE)
            ->when($branchId, fn ($q) => $q->whereHas('lead', fn ($lq) => $lq->where('branch_id', $branchId)))
            ->count();

        $quotesAccepted = Quotation::query()
            ->where('status', Quotation::STATUS_ACCEPTED)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $converted = (clone $leads)->whereNotNull('converted_customer_id')->count();

        $targets = SalesTarget::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('period_year', (int) now()->format('Y'))
            ->where('period_month', (int) now()->format('n'))
            ->get();

        return [
            'leads_total' => (clone $leads)->count(),
            'by_stage' => $byStage,
            'open_follow_ups' => $openFollowUps,
            'overdue_follow_ups' => $overdueFollowUps,
            'quotes_accepted' => $quotesAccepted,
            'converted' => $converted,
            'pipeline_value' => (float) (clone $leads)
                ->whereNotIn('pipeline_stage', [Lead::STAGE_LOST, Lead::STAGE_CANCELLED])
                ->sum(DB::raw('COALESCE(opportunity_value, lead_value, 0)')),
            'estimated_mrr' => (float) (clone $leads)
                ->whereNotIn('pipeline_stage', [Lead::STAGE_LOST, Lead::STAGE_CANCELLED])
                ->sum('estimated_mrr'),
            'targets' => $targets,
        ];
    }
}
