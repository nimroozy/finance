<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\User;

class ServiceDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?User $user = null, ?int $branchId = null): array
    {
        $query = Service::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $base = clone $query;

        return [
            'total' => (clone $base)->count(),
            'by_commercial_status' => (clone $base)->selectRaw('commercial_status, count(*) as count')
                ->groupBy('commercial_status')->pluck('count', 'commercial_status'),
            'by_operational_status' => (clone $base)->selectRaw('operational_status, count(*) as count')
                ->groupBy('operational_status')->pluck('count', 'operational_status'),
            'active_mrr' => (clone $base)->where('commercial_status', Service::COMMERCIAL_ACTIVE)->sum('mrr'),
            'suspended' => (clone $base)->where('commercial_status', Service::COMMERCIAL_SUSPENDED)->count(),
            'pending_activation' => (clone $base)->where('commercial_status', Service::COMMERCIAL_PENDING_ACTIVATION)->count(),
            'expiring_30_days' => (clone $base)->whereNotNull('expiration_date')
                ->whereBetween('expiration_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
            'radius_enabled' => (bool) config('service_lifecycle.radius.enabled', false),
        ];
    }
}
