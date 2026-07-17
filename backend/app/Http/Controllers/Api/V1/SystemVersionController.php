<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\DeploymentInfo;
use Illuminate\Http\JsonResponse;

class SystemVersionController extends Controller
{
    public function __invoke(DeploymentInfo $info): JsonResponse
    {
        return ApiResponse::success($info->toArray());
    }
}
