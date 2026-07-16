<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BranchCashbox;
use App\Models\CashReconciliationRun;
use App\Services\Cash\CashReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CashReconciliationController extends Controller
{
    public function __construct(private CashReconciliationService $reconciliations) {}
    public function index() { return ApiResponse::success(CashReconciliationRun::query()->latest('reconciliation_date')->paginate()); }
    public function run(Request $request) { $d = $request->validate(['cashbox_id' => 'required|integer', 'date' => 'required|date', 'counted_amount' => 'nullable|numeric']); return ApiResponse::success($this->reconciliations->run(BranchCashbox::findOrFail($d['cashbox_id']), $d['date'], $request->user(), $d['counted_amount'] ?? null)); }
}
