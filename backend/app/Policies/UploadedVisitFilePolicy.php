<?php

namespace App\Policies;

use App\Models\UploadedVisitFile;
use App\Models\User;

class UploadedVisitFilePolicy
{
    public function view(User $user, UploadedVisitFile $file): bool
    {
        if (! $user->can('evidence.view')) {
            return false;
        }

        $visit = $file->visit;

        if (! $visit) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        if ($user->isCollectorOnly()) {
            return $user->collector && (int) $user->collector->id === (int) $visit->collector_id;
        }

        return in_array((int) $visit->branch_id, $user->branchIds(), true);
    }

    public function upload(User $user): bool
    {
        return $user->can('evidence.upload');
    }
}
