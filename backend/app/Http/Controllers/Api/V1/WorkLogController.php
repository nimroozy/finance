<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\Task;
use App\Models\Tickets\Ticket;
use App\Models\Tickets\WorkLog;
use App\Services\WorkLogs\WorkLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkLogController extends Controller
{
    public function __construct(private WorkLogService $workLogs) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('tickets.view') || Auth::user()->can('tasks.view'), 403);

        $page = WorkLog::query()
            ->when($request->filled('ticket_id'), fn ($q) => $q->where('ticket_id', $request->integer('ticket_id')))
            ->when($request->filled('task_id'), fn ($q) => $q->where('task_id', $request->integer('task_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('tickets.update') || Auth::user()->can('tasks.complete'), 403);

        $data = $request->validate([
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'work_type' => ['nullable', 'string', 'max:64'],
            'internal_note' => ['nullable', 'string'],
            'customer_visible_note' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'result' => ['nullable', 'string', 'max:64'],
            'follow_up_required' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['ticket_id'])) {
            $ticket = Ticket::query()->findOrFail($data['ticket_id']);
            $this->authorize('view', $ticket);
        }
        if (! empty($data['task_id'])) {
            $task = Task::query()->findOrFail($data['task_id']);
            $this->authorize('view', $task);
        }

        try {
            $log = $this->workLogs->create($data, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success($log, null, 201);
    }
}
