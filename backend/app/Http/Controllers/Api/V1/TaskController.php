<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Tickets\Task;
use App\Models\User;
use App\Services\Tasks\TaskAssignmentWorkflowService;
use App\Services\Tasks\TaskService;
use App\Support\ApiResponse;
use App\Support\StatusTransitionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $tasks,
        private TaskAssignmentWorkflowService $workflow,
        private StatusTransitionPresenter $transitions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);
        $user = Auth::user();

        $query = Task::query()->with(['assignee:id,name', 'ticket:id,ticket_number,subject']);
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('ticket_id'), fn ($q) => $q->where('ticket_id', $request->integer('ticket_id')))
            ->when($request->filled('installation_id'), fn ($q) => $q->where('installation_id', $request->integer('installation_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->integer('assignee_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')));

        if ($request->boolean('my') || $request->input('my') === '1') {
            $query->where('assignee_id', $user->id);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('assignee_id');
        }

        if ($request->boolean('overdue')) {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', Task::TERMINAL_STATUSES);
        }

        if ($request->boolean('today')) {
            $query->whereDate('due_at', now()->toDateString());
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('task_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            $page->through(fn (Task $t) => (new TaskResource($t))->resolve())
        );
    }

    public function my(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);
        $user = Auth::user();

        $page = Task::query()
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::TERMINAL_STATUSES)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            $page->through(fn (Task $t) => (new TaskResource($t))->resolve())
        );
    }

    public function show(int $id): JsonResponse
    {
        $task = Task::with([
            'assignee:id,name,email',
            'dependsOnTasks',
            'statusTransitions',
            'attachments',
            'workLogs' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'ticket:id,ticket_number,subject,status',
        ])->findOrFail($id);
        $this->authorize('view', $task);

        $payload = (new TaskResource($task))->resolve();
        $payload['allowed_transitions'] = $this->transitions->forTask($task, Auth::user());
        $payload['work_logs'] = $task->workLogs;
        $payload['attachments_summary'] = $task->attachments->map(fn ($a) => [
            'id' => $a->id,
            'uuid' => $a->uuid,
            'original_name' => $a->original_name,
            'mime' => $a->mime,
            'size' => $a->size,
            'kind' => $a->kind,
            'created_at' => optional($a->created_at)?->toIso8601String(),
        ])->values();

        return ApiResponse::success($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', Rule::in([Task::TYPE_FIELD, Task::TYPE_OFFICE])],
            'priority' => ['nullable', 'string'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'installation_id' => ['nullable', 'integer', 'exists:installations,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'due_at' => ['nullable', 'date'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['integer', 'exists:tasks,id'],
            'checklist' => ['nullable', 'array'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $depends = $data['depends_on'] ?? [];
        unset($data['depends_on']);

        $task = $this->tasks->create($data, Auth::user(), $depends);

        return ApiResponse::success(new TaskResource($task), null, 201);
    }

    public function offer(Request $request, int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('assign', $task);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $task = $this->workflow->offer($task, User::findOrFail($data['user_id']), Auth::user(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TaskResource($task));
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('accept', $task);
        $data = $request->validate(['reason' => ['nullable', 'string']]);

        try {
            $task = $this->workflow->accept($task, Auth::user(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TaskResource($task));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('accept', $task);
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        try {
            $task = $this->workflow->reject($task, Auth::user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TaskResource($task));
    }

    public function startTravel(Request $request, int $id): JsonResponse
    {
        return $this->run($id, 'complete', fn (Task $t) => $this->workflow->startTravel($t, Auth::user(), $request->input('reason')));
    }

    public function arrive(Request $request, int $id): JsonResponse
    {
        return $this->run($id, 'complete', fn (Task $t) => $this->workflow->arrive($t, Auth::user(), $request->input('reason')));
    }

    public function start(Request $request, int $id): JsonResponse
    {
        return $this->run($id, 'complete', fn (Task $t) => $this->workflow->start($t, Auth::user(), $request->input('reason')));
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        return $this->run($id, 'complete', fn (Task $t) => $this->workflow->complete($t, Auth::user(), $request->input('reason')));
    }

    public function block(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        return $this->run($id, 'complete', fn (Task $t) => $this->workflow->block($t, Auth::user(), $data['reason']));
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        return $this->run($id, 'verify', fn (Task $t) => $this->workflow->verify($t, Auth::user(), $request->input('reason')));
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize('reassign', $task);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:1'],
        ]);

        try {
            $task = $this->workflow->reassign($task, User::findOrFail($data['user_id']), Auth::user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TaskResource($task));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:1']]);

        return $this->run($id, 'cancel', fn (Task $t) => $this->workflow->cancel($t, Auth::user(), $data['reason']));
    }

    /**
     * @param  callable(Task): Task  $callback
     */
    private function run(int $id, string $ability, callable $callback): JsonResponse
    {
        $task = Task::query()->findOrFail($id);
        $this->authorize($ability, $task);

        try {
            $task = $callback($task);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TaskResource($task));
    }
}
