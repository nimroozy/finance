<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Services\Crm\CrmTimelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmTimelineController extends Controller
{
    public function __construct(private CrmTimelineService $timeline) {}

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.leads.view'), 403);

        $lead = Lead::query()->findOrFail($id);

        return ApiResponse::success(
            $this->timeline->forLead($lead, Auth::user(), $request->integer('limit', 100))
        );
    }
}
