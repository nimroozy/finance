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
use App\Models\Tickets\Task;
use App\Models\Tickets\TaskStatusTransition;
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
            $from = $task->status;
            $this->assertTransition($task, Task::STATUS_OFFERED);
            $task->assignee_id = $to->id;
            $task->status = Task::STATUS_OFFERED;
            $task->save();
            $this->record($task, $from, Task::STATUS_OFFERED, $actor, $reason, 'offer');
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
            if ($task->assignee_id && $task->assignee_id !== $user->id) {
                throw new InvalidArgumentException('Task was offered to a different user.');
            }

            $from = $task->status;
            $task->assignee_id = $user->id;
            $task->status = Task::STATUS_ACCEPTED;
            $task->save();
            $this->record($task, $from, Task::STATUS_ACCEPTED, $user, $reason, 'accept');
            TaskAccepted::dispatch($task->id, (int) $task->branch_id, $user->id);

            return $task;
        }, 'task.accepted', $reason);
    }

    public function reject(Task $task, User $user, string $reason): Task
    {
        $this->requireReason($reason, 'reject');

        return $this->mutate($task, function (Task $task) use ($user, $reason) {
            if ($task->status !== Task::STATUS_OFFERED) {
                throw new InvalidArgumentException('Only offered tasks can be rejected.');
            }

            $from = $task->status;
            $task->status = Task::STATUS_REJECTED;
            $task->assignee_id = null;
            $task->save();
            $this->record($task, $from, Task::STATUS_REJECTED, $user, $reason, 'reject');
            TaskRejected::dispatch($task->id, (int) $task->branch_id, $user->id);

            return $task;
        }, 'task.rejected', $reason);
    }

    public function reassign(Task $task, User $to, ?User $actor = null, string $reason = ''): Task
    {
        $this->requireReason($reason, 'reassign');

        return $this->mutate($task, function (Task $task) use ($to, $actor, $reason) {
            $fromStatus = $task->status;
            $task->assignee_id = $to->id;
            if (in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_REJECTED, Task::STATUS_FAILED], true)) {
                $task->status = Task::STATUS_OFFERED;
            } elseif ($task->status === Task::STATUS_OFFERED) {
                // keep offered, new assignee
            } else {
                $task->status = Task::STATUS_OFFERED;
            }
            $task->save();
            $this->record($task, $fromStatus, $task->status, $actor, $reason, 'reassign');
            TaskReassigned::dispatch($task->id, (int) $task->branch_id, $to->id);

            return $task;
        }, 'task.reassigned', $reason);
    }

    public function startTravel(Task $task, User $actor, ?string $reason = null): Task
    {
        $this->assertField($task);

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            $this->dependencies->assertCanStart($task);
            $from = $task->status;
            $this->assertTransition($task, Task::STATUS_TRAVELLING);
            $task->status = Task::STATUS_TRAVELLING;
            $task->save();
            $this->record($task, $from, Task::STATUS_TRAVELLING, $actor, $reason, 'start_travel');

            return $task;
        }, 'task.travelling', $reason);
    }

    public function arrive(Task $task, User $actor, ?string $reason = null): Task
    {
        $this->assertField($task);

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            if (! in_array($task->status, [Task::STATUS_TRAVELLING, Task::STATUS_ACCEPTED, Task::STATUS_SCHEDULED], true)) {
                throw new InvalidArgumentException('Task must be travelling (or accepted/scheduled) before arrive.');
            }
            $from = $task->status;
            $task->status = Task::STATUS_ARRIVED;
            $task->save();
            $this->record($task, $from, Task::STATUS_ARRIVED, $actor, $reason, 'arrive');

            return $task;
        }, 'task.arrived', $reason);
    }

    public function start(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $this->assertAssignee($task, $actor);
            $this->dependencies->assertCanStart($task);

            if ($task->isField()
                && ! in_array($task->status, [
                    Task::STATUS_ARRIVED,
                    Task::STATUS_ACCEPTED,
                    Task::STATUS_TRAVELLING,
                    Task::STATUS_PENDING,
                ], true)
            ) {
                throw new InvalidArgumentException('Field tasks should arrive (or be accepted) before start.');
            }

            $from = $task->status;
            $this->assertTransition($task, Task::STATUS_IN_PROGRESS);
            $task->status = Task::STATUS_IN_PROGRESS;
            $task->started_at ??= now();
            $task->save();

            $this->record($task, $from, Task::STATUS_IN_PROGRESS, $actor, $reason, 'start');
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

            $from = $task->status;
            $task->status = Task::STATUS_COMPLETED;
            $task->completed_at = now();
            if ($reason) {
                $task->completion_notes = $reason;
            }
            $task->save();

            $this->record($task, $from, Task::STATUS_COMPLETED, $actor, $reason, 'complete');
            TaskCompleted::dispatch($task->id, (int) $task->branch_id);

            return $task;
        }, 'task.completed', $reason);
    }

    public function verify(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            if (! in_array($task->status, [Task::STATUS_COMPLETED, Task::STATUS_VERIFICATION_PENDING], true)) {
                throw new InvalidArgumentException('Only completed tasks can be verified.');
            }

            $from = $task->status;
            if ($task->status === Task::STATUS_COMPLETED && $task->canTransitionTo(Task::STATUS_VERIFICATION_PENDING)) {
                $task->status = Task::STATUS_VERIFICATION_PENDING;
                $task->save();
                $this->record($task, $from, Task::STATUS_VERIFICATION_PENDING, $actor, $reason, 'verify');
                $from = Task::STATUS_VERIFICATION_PENDING;
            }

            $task->status = Task::STATUS_APPROVED;
            $task->approver_id = $actor->id;
            $task->save();

            $this->record($task, $from, Task::STATUS_APPROVED, $actor, $reason, 'approve');
            TaskVerified::dispatch($task->id, (int) $task->branch_id);

            return $task;
        }, 'task.verified', $reason);
    }

    public function block(Task $task, User $actor, string $reason): Task
    {
        $this->requireReason($reason, 'block');

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            $from = $task->status;
            $this->assertTransition($task, Task::STATUS_BLOCKED);
            $task->status = Task::STATUS_BLOCKED;
            $task->save();
            $this->record($task, $from, Task::STATUS_BLOCKED, $actor, $reason, 'block');
            TaskBlocked::dispatch($task->id, (int) $task->branch_id, $reason);

            return $task;
        }, 'task.blocked', $reason);
    }

    public function cancel(Task $task, User $actor, string $reason): Task
    {
        $this->requireReason($reason, 'cancel');

        return $this->mutate($task, function (Task $task) use ($actor, $reason) {
            if ($task->isTerminal()) {
                throw new InvalidArgumentException('Terminal tasks cannot be cancelled.');
            }

            $from = $task->status;
            $this->assertTransition($task, Task::STATUS_CANCELLED);
            $task->status = Task::STATUS_CANCELLED;
            $task->save();
            $this->record($task, $from, Task::STATUS_CANCELLED, $actor, $reason, 'cancel');

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
        ?string $from,
        string $to,
        ?User $actor,
        ?string $reason,
        ?string $source,
    ): void {
        TaskStatusTransition::query()->create([
            'task_id' => $task->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->id,
            'reason' => $reason,
            'source' => $source,
            'created_at' => now(),
        ]);
    }

    private function assertTransition(Task $task, string $to): void
    {
        if (! $task->canTransitionTo($to)) {
            throw new InvalidArgumentException("Invalid task status transition [{$task->status} → {$to}].");
        }
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
        if (! $task->isField()) {
            throw new InvalidArgumentException('Travel/arrive applies only to field tasks.');
        }
    }

    private function requireReason(string $reason, string $action): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException("A reason is required to {$action} a task.");
        }
    }
}
