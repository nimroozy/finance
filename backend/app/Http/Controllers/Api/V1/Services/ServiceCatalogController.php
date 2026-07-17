<?php

namespace App\Http\Controllers\Api\V1\Services;

use App\Http\Controllers\Controller;
use App\Models\Services\ServiceAccessTechnology;
use App\Models\Services\ServiceSlaTemplate;
use App\Models\Services\ServiceType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ServiceCatalogController extends Controller
{
    public function types(): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view'), 403);

        return ApiResponse::success(ServiceType::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function technologies(): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view'), 403);

        return ApiResponse::success(ServiceAccessTechnology::query()->where('is_active', true)->orderBy('code')->get());
    }

    public function slaTemplates(): JsonResponse
    {
        abort_unless(Auth::user()?->can('services.view') || Auth::user()?->can('services.sla.manage'), 403);

        return ApiResponse::success(ServiceSlaTemplate::query()->where('is_active', true)->orderBy('name')->get());
    }
}
