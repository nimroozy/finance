<?php

namespace App\Http\Controllers\Api\V1\Ownership;

use App\Http\Controllers\Controller;
use App\Models\CustomerCollectorOwnership;
use App\Models\CustomerWorkQueue;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectorOwnershipController extends Controller
{
    public function permanentCustomers(Request $request): JsonResponse
    {
        $collectorId = $request->user()->collector?->id;
        abort_unless($collectorId, 403);
        $rows = CustomerCollectorOwnership::query()->active()->where('collector_id', $collectorId)
            ->with(['customer', 'branch'])->orderBy('id')->paginate(50);
        return ApiResponse::success($rows);
    }

    public function debtors(Request $request): JsonResponse
    {
        $collectorId = $request->user()->collector?->id;
        abort_unless($collectorId, 403);
        $rows = CustomerWorkQueue::query()
            ->with('customer')
            ->where('effective_collector_id', $collectorId)
            ->where('open_invoice_count', '>', 0)
            ->orderByDesc('days_overdue')
            ->paginate(50);
        return ApiResponse::success($rows);
    }
}
