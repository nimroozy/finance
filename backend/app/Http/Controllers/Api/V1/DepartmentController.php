<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Org\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('tickets.view') || Auth::user()->can('tasks.view'), 403);

        $user = Auth::user();
        $query = Department::query()->where('is_active', true)->with('teams');

        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->whereIn('branch_id', $user->branchIds())->orWhereNull('branch_id');
            });
        }

        if ($request->filled('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->integer('branch_id'))->orWhereNull('branch_id');
            });
        }

        return ApiResponse::success($query->orderBy('code')->get());
    }
}
