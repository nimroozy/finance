<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\User;

class ServiceReportService
{
    /**
     * @return array<string, mixed>
     */
    public function statusReport(?User $user = null, ?int $branchId = null): array
    {
        $query = Service::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        return [
            'commercial' => (clone $query)->selectRaw('commercial_status, count(*) as count, coalesce(sum(mrr),0) as mrr')
                ->groupBy('commercial_status')->get(),
            'operational' => (clone $query)->selectRaw('operational_status, count(*) as count')
                ->groupBy('operational_status')->get(),
            'billing' => (clone $query)->selectRaw('billing_status, count(*) as count')
                ->groupBy('billing_status')->get(),
            'by_type' => (clone $query)->selectRaw('service_type_code, count(*) as count')
                ->groupBy('service_type_code')->get(),
        ];
    }
}
