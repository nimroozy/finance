<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->with('branch:id,code,name_en')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('contact_name', 'like', $search)
                        ->orWhere('company_name', 'like', $search)
                        ->orWhere('customer_number', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('mobile', 'like', $search)
                        ->orWhere('whatsapp_number', 'like', $search)
                        ->orWhere('zoho_contact_id', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('sync_status'), fn ($q) => $q->where('sync_status', $request->string('sync_status')))
            ->when($request->boolean('exclude_unmapped', true) && $this->isGlobalUser(), function ($q) {
                // Global users still exclude unmapped from default list unless asked.
                $q->where('is_unmapped', false);
            })
            ->orderBy('contact_name')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($customers->items(), [
            'current_page' => $customers->currentPage(),
            'last_page' => $customers->lastPage(),
            'per_page' => $customers->perPage(),
            'total' => $customers->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::query()->with(['branch', 'customFields'])->findOrFail($id);

        return ApiResponse::success($customer);
    }

    public function unmapped(Request $request): JsonResponse
    {
        $customers = Customer::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('is_unmapped', true)
                    ->orWhere(function ($inner) {
                        $inner->whereNull('branch_id')
                            ->where('status', Customer::STATUS_UNMAPPED);
                    });
            })
            ->orderBy('contact_name')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($customers->items(), [
            'current_page' => $customers->currentPage(),
            'last_page' => $customers->lastPage(),
            'per_page' => $customers->perPage(),
            'total' => $customers->total(),
        ]);
    }

    public function mapBranch(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::withoutGlobalScopes()->findOrFail($id);
        $updated = app(\App\Services\Mapping\CustomerBranchResolutionService::class)
            ->setAdministratorOverride($customer, (int) $data['branch_id'], Auth::user(), $data['reason'] ?? null);

        return ApiResponse::success($updated->load('branch'));
    }

    protected function isGlobalUser(): bool
    {
        $user = Auth::user();

        return $user && ($user->isSuperAdmin() || $user->isCentralFinanceAdmin());
    }
}
