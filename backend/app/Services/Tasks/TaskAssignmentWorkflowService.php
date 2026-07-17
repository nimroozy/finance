<?php

namespace App\Services\Tasks;

use App\Events\TaskAccepted;
use App\Events\TaskBlocked;
use App\Events\TaskCompleted;
use App\Events\TaskOffered;
use App\Events\TaskReassigned;
use App\Events\TaskRejected;
use App\Events\TaskStarted;
use App\Events\TaskVerified;
use App\Models\Task;
use App\Models\TaskAssignmentEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskAssignmentWorkflowService
{
    public function __construct(
        private TaskDependencyService $dependencies,
        private AuditLogger $audit,
    ) {}

    public function offer(Task $task, User $to, ?User $actor = null, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($to, $actor, $reason) {
            $task->offered_to_id = $to->id;
            $task->offered_at = now();
            $task->status = Task::STATUS_OFFERED;
            $task->save();

            $this->record($task, 'offer', $actor, null, $to->id, $reason);
            TaskOffered::dispatch($task->id, (int) $task->branch_id, $to->id);

            return $task;
        }, 'task.offered', $reason);
    }

    public function accept(Task $task, User $user, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($user, $reason) {
            if ($task->status !== Task::STATUS_OFFERED) {
                throw new InvalidArgumentException('Only offered tasks can be accepted.');
            }
            if ($task->offered_to_id && $task->offered_to_id !== $user->id) {
                throw new InvalidArgumentException('Task was offered to a different user.');
            }

            $task->assignee_id = $user->id;
            $task->accepted_at = now();
            $task->status = Task::STATUS_ACCEPTED;
            $task->save();

            $this->record($task, 'accept', $user, $task->offered_to_id, $user->id, $reason);
            TaskAccepted::dispatch($task->id, (int) $task->branch_id, $user->id);

            return $task;
        }, 'task.accepted', $reason);
    }

    public function reject(Task $task, User $user, string $reason): Task
    {
        return $this->mutate($task, function (Task $task) use ($user, $reason) {
            if ($task->status !== Task::STATUS_OFFERED) {
                throw new InvalidArgumentException('Only offered tasks can be rejected.');
            }

            $from = $task->offered_to_id;
            $task->reject_reason = $reason;
            $task->offered_to_id = null;
            $task->status = Task::STATUS_REJECTED;
            $task->save();

            $this->record($task, 'reject', $user, $from, null, $reason);
            TaskRejected::dispatch($task->id, (int) $task->branch_id, $user->id);

            return $task;
        }, 'task.rejected', $reason);
    }

    public function reassign(Task $task, User $to, ?User $actor = null, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($to, $actor, $reason) {
            $from = $task->assignee_id;
            $task->assignee_id = $to->id;
            $task->offered_to_id = null;
            if (in_array($task->status, [Task::STATUS_OPEN, Task::STATUS_REJECTED, Task::STATUS_OFFERED], true)) {
                $task->status = Task::STATUS_ACCEPTED;
                $task->accepted_at = now();
            }
            $task->save();

            $this->record($task, 'reassign', $actor, $from, $to->id, $reason);
            TaskReassigned::dispatch($task->id, (int) $task->branch_id, $to->id);

            return $task;
        }, 'task.reassigned', $reason);
    }

    public function travel(Task $task, User $actor, ?string $reason = null): Task
    {
        $this->assertField($task);

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            $this->dependencies->assertCanStart($task);
            $task->status = Task::STATUS_TRAVELING;
            $task->traveled_at = now();
            $task->save();
            $this->record($task, 'travel', $actor, null, $actor->id, $reason);

            return $task;
        }, 'task.traveling', $reason);
    }

    public function arrive(Task $task, User $actor, ?string $reason = null): Task
    {
        $this->assertField($task);

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            if (! in_array($task->status, [Task::STATUS_TRAVELING, Task::STATUS_ACCEPTED], true)) {
                throw new InvalidArgumentException('Task must be traveling (or accepted) before arrive.');
            }
            $task->status = Task::STATUS_ARRIVED;
            $task->arrived_at = now();
            $task->save();
            $this->record($task, 'arrive', $actor, null, $actor->id, $reason);

            return $task;
        }, 'task.arrived', $reason);
    }

    public function start(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            $this->dependencies->assertCanStart($task);

            if ($task->work_type === Task::WORK_FIELD
                && ! in_array($task->status, [Task::STATUS_ARRIVED, Task::STATUS_ACCEPTED, Task::STATUS_TRAVELING], true)) {
                throw new InvalidArgumentException('Field tasks should arrive (or be accepted) before start.');
            }

            $task->status = Task::STATUS_IN_PROGRESS;
            $task->started_at = now();
            $task->block_reason = null;
            $task->save();

            $this->record($task, 'start', $actor, null, $actor->id, $reason);
            TaskStarted::dispatch($task->id, (int) $task->branch_id);

            return $task;
        }, 'task.started', $reason);
    }

    public function complete(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            if ($task->status !== Task::STATUS_IN_PROGRESS) {
                throw new InvalidArgumentException('Only in-progress tasks can be completed.');
            }

            $task->status = Task::STATUS_COMPLETED;
            $task->completed_at = now();
            $task->save();

            $this->record($task, 'complete', $actor, null, $actor->id, $reason);
            TaskCompleted::dispatch($task->id, (int) $task->branch_id);

            return $task;
        }, 'task.completed', $reason);
    }

    public function verify(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            if ($task->status !== Task::STATUS_COMPLETED) {
                throw new InvalidArgumentException('Only completed tasks can be verified.');
            }

            $task->status = Task::STATUS_VERIFIED;
            $task->verified_at = now();
            $task->verified_by = $actor->id;
            $task->save();

            $this->record($task, 'verify', $actor, null, $actor->id, $reason);
            TaskVerified::dispatch($task->id, (int) $task->branch_id);

            return $task;
        }, 'task.verified', $reason);
    }

    public function block(Task $task, User $actor, string $reason): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $task->status = Task::STATUS_BLOCKED;
            $task->block_reason = $reason;
            $task->save();

            $this->record($task, 'block', $actor, null, $actor->id, $reason);
            TaskBlocked::dispatch($task->id, (int) $task->branch_id, $reason);

            return $task;
        }, 'task.blocked', $reason);
    }

    public function cancel(Task $task, User $actor, string $reason): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            if ($task->isTerminal()) {
                throw new InvalidArgumentException('Terminal tasks cannot be cancelled.');
            }

            $task->status = Task::STATUS_CANCELLED;
            $task->cancel_reason = $reason;
            $task->save();

            $this->record($task, 'cancel', $actor, null, $actor->id, $reason);

            return $task;
        }, 'task.cancelled', $reason);
    }

    /**
     * @param  callable(Task): Task  $callback
     */
    private function mutate(Task $task, callable $callback, string $auditAction, ?string $reason): Task
    {
        return DB::transaction(function () use ($task, $callback, $auditAction, $reason) {
            /** @var Task $locked */
            $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            $updated = $callback($locked);
            $this->audit->log($auditAction, $updated, null, [
                'status' => $updated->status,
                'assignee_id' => $updated->assignee_id,
            ], $updated->branch_id, $reason);

            return $updated->fresh();
        });
    }

    private function record(
        Task $task,
        string $action,
        ?User $actor,
        ?int $fromUserId,
        ?int $toUserId,
        ?string $reason,
    ): void {
        TaskAssignmentEvent::create([
            'task_id' => $task->id,
            'action' => $action,
            'actor_id' => $actor?->id,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function assertAssignee(Task $task, User $actor): void
    {
        if ($task->assignee_id && $task->assignee_id !== $actor->id) {
            throw new InvalidArgumentException('Only the assignee can perform this task action.');
        }
        if (! $task->assignee_id) {
            $task->assignee_id = $actor->id;
        }
    }

    private function assertField(Task $task): void
    {
        if ($task->work_type !== Task::WORK_FIELD) {
            throw new InvalidArgumentException('Travel/arrive applies only to field tasks.');
        }
    }
}
