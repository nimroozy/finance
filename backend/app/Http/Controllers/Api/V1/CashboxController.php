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

    public function index(Request $request)
    {
        $page = BranchCashbox::query()
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy('branch_id')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::paginated($page);
    }

    public function show(BranchCashbox $cashbox)
    {
        return ApiResponse::success($cashbox->load('transactions'));
    }

    public function ensure(Request $request)
    {
        $d = $request->validate([
            'branch_id' => 'required|integer',
            'currency' => 'nullable|string|size:3',
        ]);

        return ApiResponse::success(
            $this->cashboxes->ensure($d['branch_id'], $d['currency'] ?? 'AFN', $request->user()),
            null,
            201,
        );
    }
}
