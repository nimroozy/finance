<?php

namespace App\Http\Controllers\Api\V1\Ownership;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchZohoAccountMapping;
use App\Models\ZohoAccount;
use App\Models\ZohoPaymentMode;
use App\Services\Ownership\BranchPaymentMappingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchPaymentMappingController extends Controller
{
    public function __construct(private BranchPaymentMappingService $service) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(BranchZohoAccountMapping::query()->with('branch')->orderBy('branch_id')->get());
    }

    public function accounts(Request $request): JsonResponse
    {
        $q = ZohoAccount::query()->orderBy('name');
        if ($request->filled('type')) {
            $type = strtolower($request->string('type'));
            $q->where(function ($inner) use ($type) {
                $inner->whereRaw('lower(account_type) like ?', ["%{$type}%"]);
            });
        }
        if ($request->boolean('active_only', true)) $q->where('is_active', true);
        return ApiResponse::success($q->get(['id', 'zoho_account_id', 'name', 'account_type', 'account_code', 'is_active']));
    }

    public function paymentModes(): JsonResponse
    {
        return ApiResponse::success(ZohoPaymentMode::query()->where('is_active', true)->orderBy('name')->get());
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'zoho_account_id' => ['required', 'string'],
            'zoho_payment_mode_id' => ['nullable', 'string'],
            'zoho_location_id' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'receipt_prefix' => ['nullable', 'string', 'max:16'],
        ]);
        $branch = Branch::withoutGlobalScopes()->findOrFail($data['branch_id']);
        $row = $this->service->upsert(
            $branch, $request->user(), $data['zoho_account_id'],
            $data['zoho_payment_mode_id'] ?? null, $data['currency'] ?? null,
            $data['receipt_prefix'] ?? null, $data['zoho_location_id'] ?? null,
        );
        return ApiResponse::success($row->load('branch'));
    }

    public function validateMapping(int $branchId): JsonResponse
    {
        $mapping = BranchZohoAccountMapping::query()->where('branch_id', $branchId)->firstOrFail();
        return ApiResponse::success($this->service->validate($mapping));
    }

    public function readiness(int $branchId): JsonResponse
    {
        return ApiResponse::success($this->service->readinessForBranch($branchId));
    }
}
