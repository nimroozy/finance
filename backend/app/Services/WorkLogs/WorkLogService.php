<?php

namespace App\Services\WorkLogs;

use App\Events\WorkLogAdded;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogAmendment;
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
        if (empty($data['branch_id']) || empty($data['body'])) {
            throw new InvalidArgumentException('Work log requires branch_id and body.');
        }
        if (empty($data['ticket_id']) && empty($data['task_id'])) {
            throw new InvalidArgumentException('Work log requires ticket_id or task_id.');
        }

        return DB::transaction(function () use ($data, $author) {
            $log = WorkLog::query()->create([
                'branch_id' => $data['branch_id'],
                'ticket_id' => $data['ticket_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'author_id' => $author->id,
                'visibility' => $data['visibility'] ?? 'internal',
                'body' => $data['body'],
                'minutes_spent' => $data['minutes_spent'] ?? null,
                'is_locked' => false,
                'meta' => $data['meta'] ?? null,
            ]);

            $this->audit->log('work_log.created', $log, null, $log->toArray(), $log->branch_id);
            WorkLogAdded::dispatch($log->id, (int) $log->branch_id, $log->ticket_id, $log->task_id);

            return $log;
        });
    }

    public function lock(WorkLog $log, ?User $actor = null): WorkLog
    {
        if ($log->is_locked) {
            return $log;
        }

        $log->is_locked = true;
        $log->locked_at = now();
        $log->save();

        $this->audit->log('work_log.locked', $log, null, ['actor_id' => $actor?->id], $log->branch_id);

        return $log;
    }

    public function amend(WorkLog $log, string $newBody, string $reason, User $actor): WorkLog
    {
        if ($log->is_locked) {
            throw new InvalidArgumentException('Locked work logs cannot be amended.');
        }

        return DB::transaction(function () use ($log, $newBody, $reason, $actor) {
            WorkLogAmendment::query()->create([
                'work_log_id' => $log->id,
                'amended_by' => $actor->id,
                'previous_body' => $log->body,
                'new_body' => $newBody,
                'reason' => $reason,
            ]);

            $old = $log->body;
            $log->body = $newBody;
            $log->save();

            $this->audit->log('work_log.amended', $log, ['body' => $old], [
                'body' => $newBody,
                'reason' => $reason,
            ], $log->branch_id, $reason);

            return $log->fresh('amendments');
        });
    }
}
