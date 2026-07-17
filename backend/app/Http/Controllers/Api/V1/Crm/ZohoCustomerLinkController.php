<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Lead;
use App\Models\Customer;
use App\Services\Crm\CrmZohoCustomerLinkService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ZohoCustomerLinkController extends Controller
{
    public function __construct(
        private CrmZohoCustomerLinkService $links,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
            'zoho_contact_id' => ['nullable', 'string', 'max:128'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $branchIds = $user->branchIds();
            if (! empty($data['branch_id']) && ! in_array((int) $data['branch_id'], $branchIds, true)) {
                return ApiResponse::error('Branch not allowed.', [], 403);
            }
            if (empty($data['branch_id']) && count($branchIds) === 1) {
                $data['branch_id'] = $branchIds[0];
            }
        }

        $rows = $this->links->searchMirror($data);

        return ApiResponse::success($rows->map(fn (Customer $c) => $this->serializeCustomer($c))->values()->all());
    }

    public function link(Request $request, int $id): JsonResponse
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('update', $lead);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);

        try {
            $lead = $this->links->linkLeadToCustomer($lead, $customer, Auth::user());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'lead_id' => $lead->id,
            'converted_customer_id' => $lead->converted_customer_id,
            'customer' => $this->serializeCustomer($customer->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCustomer(Customer $c): array
    {
        return [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'zoho_contact_id' => $c->zoho_contact_id,
            'customer_number' => $c->customer_number,
            'contact_name' => $c->contact_name,
            'company_name' => $c->company_name,
            'phone' => $c->phone,
            'mobile' => $c->mobile,
            'email' => $c->email,
            'status' => $c->status,
            'sync_status' => $c->sync_status,
        ];
    }
}
