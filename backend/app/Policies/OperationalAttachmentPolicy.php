<?php

namespace App\Policies;

use App\Models\Tickets\OperationalAttachment;
use App\Models\User;

class OperationalAttachmentPolicy
{
    public function view(User $user, OperationalAttachment $attachment): bool
    {
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return true;
        }

        return in_array((int) $attachment->branch_id, $user->branchIds(), true);
    }

    public function upload(User $user): bool
    {
        return $user->can('attachments.upload');
    }

    public function delete(User $user, OperationalAttachment $attachment): bool
    {
        return $user->can('attachments.delete') && $this->view($user, $attachment);
    }
}
