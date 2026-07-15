<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?int $branchId = null,
    ): AppNotification {
        return AppNotification::create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function markRead(AppNotification $notification): AppNotification
    {
        $notification->markRead();

        return $notification->fresh();
    }

    public function markAllRead(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Notify branch finance managers (and super/central admins subscribed via branch access).
     *
     * @param  array<string, mixed>|null  $data
     */
    public function notifyBranchManagers(
        int $branchId,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
    ): void {
        $managers = User::query()
            ->permission('escalations.view')
            ->where(function ($q) use ($branchId) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', [
                    User::ROLE_SUPER_ADMIN,
                    User::ROLE_CENTRAL_FINANCE,
                ]))->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branchId));
            })
            ->get();

        foreach ($managers as $manager) {
            $this->notify($manager, $type, $title, $body, $data, $branchId);
        }
    }
}
