<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectorWalletTransaction;
use App\Services\Wallets\CollectorWalletService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(protected CollectorWalletService $wallets) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('wallets.view'), 403);

        $user = Auth::user();
        $collectorId = $request->integer('collector_id') ?: $user->collector?->id;

        if ($user->isCollectorOnly()) {
            $collectorId = $user->collector?->id;
        }

        abort_if(! $collectorId, 422, 'collector_id is required.');

        $branchId = $request->integer('branch_id') ?: ($user->branchIds()[0] ?? null);
        abort_if(! $branchId, 422, 'branch_id is required.');

        $currency = strtoupper((string) $request->input('currency', config('payments.default_currency', 'AFN')));
        $wallet = $this->wallets->getOrCreate((int) $collectorId, (int) $branchId, $currency);

        return ApiResponse::success($wallet);
    }

    public function transactions(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('wallets.view'), 403);

        $user = Auth::user();
        $collectorId = $request->integer('collector_id') ?: $user->collector?->id;
        if ($user->isCollectorOnly()) {
            $collectorId = $user->collector?->id;
        }
        abort_if(! $collectorId, 422, 'collector_id is required.');

        $page = CollectorWalletTransaction::query()
            ->where('collector_id', $collectorId)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }
}
