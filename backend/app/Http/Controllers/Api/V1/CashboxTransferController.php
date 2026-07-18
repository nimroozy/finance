<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BranchCashbox;
use App\Models\BranchCashboxTransfer;
use App\Services\Cash\CashboxTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CashboxTransferController extends Controller
{
    public function __construct(private CashboxTransferService $transfers) {}
    public function index(Request $request)
    {
        $page = BranchCashboxTransfer::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::paginated($page);
    }
    public function draft(Request $request) { $d = $request->validate(['from_cashbox_id' => 'required|integer', 'to_cashbox_id' => 'nullable|integer', 'amount' => 'required|numeric', 'type' => 'nullable|in:cashbox_transfer,bank_deposit', 'bank_name' => 'nullable|string', 'bank_reference' => 'nullable|string', 'deposited_on' => 'nullable|date', 'notes' => 'nullable|string']); $from = BranchCashbox::findOrFail($d['from_cashbox_id']); $to = isset($d['to_cashbox_id']) ? BranchCashbox::findOrFail($d['to_cashbox_id']) : null; return ApiResponse::success($this->transfers->draft($request->user(), $from, $to, $d['amount'], $d['type'] ?? 'cashbox_transfer', $d), null, 201); }
    public function submit(Request $request, BranchCashboxTransfer $transfer) { return ApiResponse::success($this->transfers->submit($transfer, $request->user())); }
    public function approve(Request $request, BranchCashboxTransfer $transfer) { return ApiResponse::success($this->transfers->approve($transfer, $request->user())); }
    public function send(Request $request, BranchCashboxTransfer $transfer) { return ApiResponse::success($this->transfers->send($transfer, $request->user())); }
    public function receive(Request $request, BranchCashboxTransfer $transfer) { return ApiResponse::success($this->transfers->receive($transfer, $request->user())); }
    public function reverse(Request $request, BranchCashboxTransfer $transfer) { $d = $request->validate(['reason' => 'required|string']); return ApiResponse::success($this->transfers->reverse($transfer, $request->user(), $d['reason'])); }
}
