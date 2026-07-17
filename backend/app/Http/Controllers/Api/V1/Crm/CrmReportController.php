<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmReportController extends Controller
{
    public function __construct(private CrmReportService $reports) {}

    public function leads(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->can('crm.reports.view'), 403);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'pipeline_stage' => ['nullable', 'string'],
            'salesperson_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return $this->reports->leadsCsv($filters);
    }
}
