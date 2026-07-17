<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\LeadSource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('crm.leads.view') || Auth::user()->can('crm.leads.create'), 403);

        $query = LeadSource::query()
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('name_en');

        return ApiResponse::success($query->get());
    }
}
