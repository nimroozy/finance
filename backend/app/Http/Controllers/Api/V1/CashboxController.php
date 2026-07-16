<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BranchCashbox;
use App\Services\Cash\BranchCashboxService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CashboxController extends Controller
{
    public function __construct(private BranchCashboxService $cashboxes) {}
    public function index() { return ApiResponse::success(BranchCashbox::query()->with('transactions')->paginate()); }
    public function show(BranchCashbox $cashbox) { return ApiResponse::success($cashbox->load('transactions')); }
    public function ensure(Request $request) { $d = $request->validate(['branch_id' => 'required|integer', 'currency' => 'nullable|string|size:3']); return ApiResponse::success($this->cashboxes->ensure($d['branch_id'], $d['currency'] ?? 'AFN', $request->user()), null, 201); }
}
