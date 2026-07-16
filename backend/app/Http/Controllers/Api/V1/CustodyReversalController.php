<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustodyConflict;
use App\Services\Cash\CustodyAwareReversalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CustodyReversalController extends Controller
{
    public function __construct(private CustodyAwareReversalService $custody) {}

    public function index(Request $request): JsonResponse
    {
        $rows = CustodyConflict::query()
            ->with(['payment:id,uuid,payment_reference,amount,currency,status,handover_status,zoho_payment_id,customer_id,collector_id,branch_id', 'handover:id,handover_number,status,approved_amount,selected_payment_total', 'requester:id,name', 'reviewer:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($rows->items(), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $conflict = CustodyConflict::query()->findOrFail($id);

        return ApiResponse::success($this->custody->detail($conflict));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $conflict = CustodyConflict::query()->findOrFail($id);
        try {
            $updated = $this->custody->approve($conflict, Auth::user(), (bool) $request->boolean('force_live_zoho'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($this->custody->detail($updated));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $conflict = CustodyConflict::query()->findOrFail($id);
        try {
            $updated = $this->custody->reject($conflict, Auth::user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($updated);
    }

    public function retryZoho(int $id): JsonResponse
    {
        $conflict = CustodyConflict::query()->findOrFail($id);
        $updated = $this->custody->retryZohoVoid($conflict, forceLiveZoho: true);

        return ApiResponse::success($this->custody->detail($updated));
    }
}
