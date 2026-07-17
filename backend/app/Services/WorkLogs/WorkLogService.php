<?php

namespace App\Services\WorkLogs;

use App\Events\WorkLogAdded;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\WorkLog;
use App\Models\Tickets\WorkLogAmendment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkLogService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $author): WorkLog
    {
        if (empty($data['ticket_id']) && empty($data['task_id'])) {
            throw new InvalidArgumentException('Work log requires ticket_id or task_id.');
        }

        if (empty($data['started_at']) && empty($data['internal_note']) && empty($data['customer_visible_note'])) {
            throw new InvalidArgumentException('Work log requires started_at or a note.');
        }

        return DB::transaction(function () use ($data, $author) {
            $branchId = null;
            if (! empty($data['ticket_id'])) {
                $branchId = Ticket::query()->whereKey($data['ticket_id'])->value('branch_id');
            } elseif (! empty($data['task_id'])) {
                $branchId = Task::query()->whereKey($data['task_id'])->value('branch_id');
            }

            $startedAt = isset($data['started_at']) ? $data['started_at'] : now();
            $endedAt = $data['ended_at'] ?? null;
            $duration = $data['duration_minutes'] ?? null;
            if ($duration === null && $endedAt) {
                $duration = now()->parse($startedAt)->diffInMinutes(now()->parse($endedAt));
            }

            $log = WorkLog::query()->create([
                'ticket_id' => $data['ticket_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'user_id' => $author->id,
                'department_id' => $data['department_id'] ?? null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_minutes' => $duration,
                'work_type' => $data['work_type'] ?? null,
                'internal_note' => $data['internal_note'] ?? ($data['body'] ?? null),
                'customer_visible_note' => $data['customer_visible_note'] ?? null,
                'gps_lat' => $data['gps_lat'] ?? null,
                'gps_lng' => $data['gps_lng'] ?? null,
                'equipment_used' => $data['equipment_used'] ?? null,
                'result' => $data['result'] ?? null,
                'follow_up_required' => (bool) ($data['follow_up_required'] ?? false),
                'is_locked' => false,
            ]);

            $this->audit->log('work_log.created', $log, null, $log->toArray(), $branchId);
            WorkLogAdded::dispatch($log->id, (int) ($branchId ?? 0), $log->ticket_id, $log->task_id);

            return $log;
        });
    }

    public function lock(WorkLog $log, ?User $actor = null): WorkLog
    {
        if ($log->is_locked) {
            return $log;
        }

        $log->is_locked = true;
        $log->save();

        $branchId = $log->ticket?->branch_id ?? $log->task?->branch_id;
        $this->audit->log('work_log.locked', $log, null, ['actor_id' => $actor?->id], $branchId);

        return $log;
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    public function amend(WorkLog $log, array $newValues, string $reason, User $actor): WorkLog
    {
        if ($log->is_locked) {
            throw new InvalidArgumentException('Locked work logs cannot be amended.');
        }

        return DB::transaction(function () use ($log, $newValues, $reason, $actor) {
            $allowed = [
                'internal_note', 'customer_visible_note', 'duration_minutes', 'work_type',
                'result', 'equipment_used', 'follow_up_required', 'ended_at',
            ];
            $changes = collect($newValues)->only($allowed)->all();
            $old = $log->only(array_keys($changes));

            WorkLogAmendment::query()->create([
                'work_log_id' => $log->id,
                'user_id' => $actor->id,
                'reason' => $reason,
                'old_values' => $old,
                'new_values' => $changes,
            ]);

            $log->fill($changes);
            $log->save();

            $branchId = $log->ticket?->branch_id ?? $log->task?->branch_id;
            $this->audit->log('work_log.amended', $log, $old, $changes, $branchId, $reason);

            return $log->fresh('amendments');
        });
    }
}
