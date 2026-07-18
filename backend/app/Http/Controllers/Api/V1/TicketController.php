<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $user = Auth::user();
        $query = Ticket::query()->with(['primaryAssignee:id,name', 'slaState']);

        if (! $user->can('tickets.view_all') && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $page = $query
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type_code'), fn ($q) => $q->where('type_code', $request->string('type_code')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            $page->through(fn (Ticket $t) => (new TicketResource($t))->resolve())
        );
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with(['primaryAssignee', 'watchers', 'slaState', 'tasks', 'statusTransitions'])->findOrFail($id);
        $this->authorize('view', $ticket);

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type_code' => ['required', 'string', 'exists:ticket_types,code'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source' => ['nullable', 'string', Rule::in(Ticket::SOURCES)],
            'priority' => ['nullable', 'string', Rule::in(Ticket::PRIORITIES)],
            'severity' => ['nullable', 'string', Rule::in(Ticket::SEVERITIES)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_number' => ['nullable', 'string', 'max:64'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'customer_location' => ['nullable', 'string'],
            'assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'assigned_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'primary_assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'whatsapp_conversation_id' => ['nullable', 'integer', 'exists:whatsapp_conversations,id'],
            'category' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array'],
            'external_reference' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()
            && ! in_array((int) $data['branch_id'], $user->branchIds(), true)) {
            return ApiResponse::error('Branch access denied.', [], 403);
        }

        $ticket = $this->tickets->create($data, $user);

        return ApiResponse::success(new TicketResource($ticket), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', Rule::in(Ticket::PRIORITIES)],
            'severity' => ['nullable', 'string', Rule::in(Ticket::SEVERITIES)],
            'category' => ['nullable', 'string', 'max:64'],
            'assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'assigned_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'internal_notes' => ['nullable', 'string'],
            'customer_visible_notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        return ApiResponse::success(new TicketResource($this->tickets->update($ticket, $data, Auth::user())));
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(Ticket::STATUSES)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'comment' => ['nullable', 'string'],
        ]);

        try {
            $ticket = $this->tickets->transition($ticket, $data['status'], Auth::user(), $data['reason'] ?? null, 'api');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('assign', $ticket);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignee = User::query()->findOrFail($data['user_id']);

        return ApiResponse::success(new TicketResource(
            $this->tickets->assign($ticket, $assignee, Auth::user(), $data['reason'] ?? null)
        ));
    }

    public function watchers(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', Rule::in(['add', 'remove'])],
        ]);

        $watcher = User::query()->findOrFail($data['user_id']);
        $action = $data['action'] ?? 'add';

        $ticket = $action === 'remove'
            ? $this->tickets->removeWatcher($ticket, $watcher, Auth::user())
            : $this->tickets->addWatcher($ticket, $watcher, Auth::user());

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('resolve', $ticket);

        $data = $request->validate([
            'resolution_summary' => ['nullable', 'string'],
            'customer_confirmation' => ['nullable', 'boolean'],
        ]);

        try {
            $ticket = $this->tickets->resolve(
                $ticket,
                Auth::user(),
                $data['resolution_summary'] ?? null,
                (bool) ($data['customer_confirmation'] ?? false),
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('close', $ticket);

        $data = $request->validate(['reason' => ['nullable', 'string']]);

        try {
            $ticket = $this->tickets->close($ticket, Auth::user(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $this->authorize('reopen', $ticket);

        $data = $request->validate(['reason' => ['nullable', 'string']]);

        try {
            $ticket = $this->tickets->reopen($ticket, Auth::user(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new TicketResource($ticket));
    }
}
