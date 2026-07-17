<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters, ?User $user = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Service::query()->with([
            'customer:id,contact_name,customer_number,company_name,phone,zoho_contact_id',
            'package:id,code,name',
            'packageVersion',
            'location:id,name,address,city',
            'installation:id,installation_number,status,address',
            'slaTemplate:id,code,name',
        ]);

        if ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $query
            ->when(! empty($filters['branch_id']), fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->when(! empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(! empty($filters['commercial_status']), fn ($q) => $q->where('commercial_status', $filters['commercial_status']))
            ->when(! empty($filters['operational_status']), fn ($q) => $q->where('operational_status', $filters['operational_status']))
            ->when(! empty($filters['billing_status']), fn ($q) => $q->where('billing_status', $filters['billing_status']))
            ->when(! empty($filters['package_id']), fn ($q) => $q->where('package_id', $filters['package_id']))
            ->when(! empty($filters['service_type_code']), fn ($q) => $q->where('service_type_code', $filters['service_type_code']));

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('service_number', 'like', $term)
                    ->orWhere('circuit_id', 'like', $term)
                    ->orWhere('customer_reference', 'like', $term)
                    ->orWhere('pppoe_username_placeholder', 'like', $term)
                    ->orWhere('static_ip', 'like', $term);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }
}
