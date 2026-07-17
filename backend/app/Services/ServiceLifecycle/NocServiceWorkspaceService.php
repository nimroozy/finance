<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceRelocation;
use App\Models\User;

class NocServiceWorkspaceService
{
    /**
     * @return array<string, mixed>
     */
    public function workspace(?User $user = null, ?int $branchId = null): array
    {
        $query = Service::query()->with(['customer:id,contact_name,customer_number', 'location', 'package:id,code,name']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $attention = (clone $query)->whereIn('operational_status', [
            Service::OPERATIONAL_OFFLINE,
            Service::OPERATIONAL_SUSPENDED,
            Service::OPERATIONAL_PENDING_INSTALL,
            Service::OPERATIONAL_READY,
        ])->orderByDesc('updated_at')->limit(100)->get();

        $pendingActivation = (clone $query)->where('commercial_status', Service::COMMERCIAL_PENDING_ACTIVATION)
            ->orderByDesc('id')->limit(50)->get();

        $branchFilter = function ($q) use ($branchId, $user) {
            if ($branchId) {
                $q->where('branch_id', $branchId);
            } elseif ($user && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
                $q->whereIn('branch_id', $user->branchIds());
            }
        };

        $openChanges = ServiceChangeRequest::query()
            ->with('service:id,service_number,branch_id,customer_id')
            ->whereIn('status', [
                ServiceChangeRequest::STATUS_REQUESTED,
                ServiceChangeRequest::STATUS_PENDING,
                ServiceChangeRequest::STATUS_TECHNICAL_REVIEW,
                ServiceChangeRequest::STATUS_FINANCE_REVIEW,
                ServiceChangeRequest::STATUS_APPROVED,
            ])
            ->whereHas('service', $branchFilter)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $openRelocations = ServiceRelocation::query()
            ->with('service:id,service_number,branch_id,customer_id')
            ->whereIn('status', [
                ServiceRelocation::STATUS_REQUESTED,
                ServiceRelocation::STATUS_IN_PROGRESS,
            ])
            ->whereHas('service', $branchFilter)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'attention_queue' => $attention,
            'pending_activation' => $pendingActivation,
            'open_change_requests' => $openChanges,
            'open_relocations' => $openRelocations,
            'radius_automation' => false,
            'note' => 'NOC workspace is operational lifecycle only — no Radius provisioning. Online requires explicit confirm.',
        ];
    }
}
