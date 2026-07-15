<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        ?int $branchId = null,
        ?string $reason = null,
    ): AuditLog {
        $userId = Auth::id();

        if ($userId === null && $entity instanceof \App\Models\User) {
            $userId = $entity->getKey();
        }

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entity ? $entity->getMorphClass() : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device' => $this->detectDevice(Request::userAgent()),
            'branch_id' => $branchId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    protected function detectDevice(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
