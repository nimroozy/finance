<?php

namespace App\Http\Controllers\Api\V1\Apps;

use App\Http\Controllers\Controller;
use App\Services\Apps\AppCountsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppCountsController extends Controller
{
    public function __construct(private AppCountsService $counts) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $user = Auth::user();
        $branchIds = null;
        if (! empty($data['branch_id'])) {
            $branchIds = [(int) $data['branch_id']];
        }

        return ApiResponse::success($this->counts->counts($user, $branchIds));
    }
}
