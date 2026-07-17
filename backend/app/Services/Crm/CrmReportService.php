<?php

namespace App\Services\Crm;

use App\Models\Crm\Lead;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function leadsCsv(array $filters = []): StreamedResponse
    {
        $query = Lead::query()
            ->with(['salesperson:id,name', 'owner:id,name'])
            ->when(! empty($filters['branch_id']), fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
            ->when(! empty($filters['pipeline_stage']), fn ($q) => $q->where('pipeline_stage', $filters['pipeline_stage']))
            ->when(! empty($filters['salesperson_id']), fn ($q) => $q->where('salesperson_id', (int) $filters['salesperson_id']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('id');

        $filename = 'crm-leads-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'lead_number', 'branch_id', 'pipeline_stage', 'company', 'contact_person',
                'phone', 'source_code', 'salesperson', 'owner', 'lead_value', 'estimated_mrr',
                'opportunity_value', 'next_follow_up_at', 'converted_customer_id', 'installation_id', 'created_at',
            ]);

            $query->chunk(200, function ($leads) use ($out) {
                foreach ($leads as $lead) {
                    fputcsv($out, [
                        $lead->lead_number,
                        $lead->branch_id,
                        $lead->pipeline_stage,
                        $lead->company,
                        $lead->contact_person,
                        $lead->phone,
                        $lead->source_code,
                        $lead->salesperson?->name,
                        $lead->owner?->name,
                        $lead->lead_value,
                        $lead->estimated_mrr,
                        $lead->opportunity_value,
                        optional($lead->next_follow_up_at)?->toIso8601String(),
                        $lead->converted_customer_id,
                        $lead->installation_id,
                        optional($lead->created_at)?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
