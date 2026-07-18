<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashHandoverRequest;
use App\Services\Cash\CashHandoverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CashHandoverController extends Controller
{
    public function __construct(private CashHandoverService $handovers) {}
    public function eligible(Request $request) { $d = $request->validate(['collector_id' => 'nullable|integer', 'branch_id' => 'required|integer', 'currency' => 'nullable|string|size:3']); $collector = $d['collector_id'] ?? $request->user()->collector?->id; abort_if(! $collector, 422, 'collector_id is required.'); return ApiResponse::success($this->handovers->eligible($collector, $d['branch_id'], $d['currency'] ?? 'AFN')); }
    public function index(Request $request)
    {
        $page = CashHandoverRequest::query()
            ->with(['items', 'collector.user:id,name', 'cashbox:id,name', 'branch:id,code,name_en,name_fa'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::paginated($page);
    }
    public function show(CashHandoverRequest $handover) { return ApiResponse::success($handover->load('items.payment', 'history', 'reviews')); }
    public function draft(Request $request) { $d = $request->validate(['collector_id' => 'nullable|integer', 'branch_id' => 'required|integer', 'currency' => 'nullable|string|size:3', 'payment_ids' => 'required|array|min:1', 'payment_ids.*' => 'integer', 'declared_amount' => 'required|numeric', 'notes' => 'nullable|string']); $collector = $d['collector_id'] ?? $request->user()->collector?->id; abort_if(! $collector, 422, 'collector_id is required.'); return ApiResponse::success($this->handovers->draft($request->user(), $collector, $d['branch_id'], $d['currency'] ?? 'AFN', $d['payment_ids'], $d['declared_amount'], $d['notes'] ?? null), null, 201); }
    public function submit(Request $request, CashHandoverRequest $handover) { return ApiResponse::success($this->handovers->submit($handover, $request->user())); }
    public function approve(Request $request, CashHandoverRequest $handover) { $d = $request->validate(['counted_amount' => 'required|numeric', 'approved_amount' => 'nullable|numeric', 'notes' => 'nullable|string']); return ApiResponse::success($this->handovers->approve($handover, $request->user(), $d['counted_amount'], $d['approved_amount'] ?? null, $d['notes'] ?? null)); }
    public function reject(Request $request, CashHandoverRequest $handover) { $d = $request->validate(['notes' => 'required|string']); return ApiResponse::success($this->handovers->reject($handover, $request->user(), $d['notes'])); }
}
