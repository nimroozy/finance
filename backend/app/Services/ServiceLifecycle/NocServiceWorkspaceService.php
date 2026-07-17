<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
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
        ])->orderByDesc('updated_at')->limit(100)->get();

        $pendingActivation = (clone $query)->where('commercial_status', Service::COMMERCIAL_PENDING_ACTIVATION)
            ->orderByDesc('id')->limit(50)->get();

        return [
            'attention_queue' => $attention,
            'pending_activation' => $pendingActivation,
            'radius_automation' => false,
            'note' => 'NOC workspace is operational lifecycle only — no Radius provisioning.',
        ];
    }
}
