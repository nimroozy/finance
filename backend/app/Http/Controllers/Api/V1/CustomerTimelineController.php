<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Timeline\CustomerTimelineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerTimelineController extends Controller
{
    public function __construct(private CustomerTimelineService $timeline) {}

    public function show(Request $request, int $customer): JsonResponse
    {
        abort_unless(Auth::user()->can('tickets.view') || Auth::user()->can('customers.view'), 403);

        $model = Customer::query()->findOrFail($customer);
        $events = $this->timeline->aggregate(
            $model,
            Auth::user(),
            $request->integer('limit', 100),
        );

        return ApiResponse::success($events);
    }
}
