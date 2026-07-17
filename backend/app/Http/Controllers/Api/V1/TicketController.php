<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Tickets\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketService;
use App\Support\ApiResponse;
use App\Support\StatusTransitionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private StatusTransitionPresenter $transitions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $user = Auth::user();
        $query = Ticket::query()->with([
            'customer:id,customer_number,contact_name,phone,mobile,branch_id',
            'branch:id,code,name_en',
            'primaryAssignee:id,name,email',
            'assignedDepartment:id,code,name_en',
            'slaState',
            'ticketType:id,code,name_en,name_fa',
        ]);

        if (! $user->can('tickets.view_all') && ! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $this->applyFilters($query, $request);

        $sort = (string) $request->input('sort', '-id');
        $this->applySort($query, $sort);

        $page = $query->paginate($request->integer('per_page', 15));

        $meta = [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'filters' => [
                'search', 'status', 'priority', 'branch_id', 'type_code', 'department_id', 'team_id',
                'assignee_id', 'customer_id', 'source', 'sla_state', 'created_from', 'created_to',
                'updated_from', 'updated_to', 'overdue', 'unassigned', 'major_incident_id', 'sort', 'per_page',
            ],
        ];

        return ApiResponse::success(
            $page->getCollection()->map(fn (Ticket $t) => (new TicketResource($t))->resolve())->values(),
            $meta
        );
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with([
            'customer:id,customer_number,contact_name,phone,mobile,branch_id',
            'branch:id,code,name_en',
            'primaryAssignee:id,name,email',
            'assignedDepartment:id,code,name_en',
            'assignedTeam:id,code,name_en',
            'watchers:id,name,email',
            'slaState',
            'ticketType:id,code,name_en,name_fa',
            'tasks' => fn ($q) => $q->orderByDesc('id')->limit(50),
            'attachments' => fn ($q) => $q->orderByDesc('id')->limit(50),
            'workLogs' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'statusTransitions' => fn ($q) => $q->orderByDesc('id')->limit(20),
        ])->findOrFail($id);
        $this->authorize('view', $ticket);

        $openRelated = Ticket::query()
            ->where('id', '!=', $ticket->id)
            ->where('customer_id', $ticket->customer_id)
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority', 'branch_id']);

        $payload = (new TicketResource($ticket))->resolve();
        $payload['allowed_transitions'] = $this->transitions->forTicket($ticket, Auth::user());
        $payload['open_related'] = $openRelated;
        $payload['recent_work_logs'] = $ticket->workLogs;
        $payload['attachments_summary'] = $ticket->attachments->map(fn ($a) => [
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

        $toStatus = $data['status'];
        if (! $ticket->canTransitionTo($toStatus)) {
            $allowed = Ticket::allowedTransitions()[$ticket->status] ?? [];

            return ApiResponse::error(
                "Invalid ticket status transition [{$ticket->status} → {$toStatus}]. Allowed: ".implode(', ', $allowed ?: ['(none)']),
                ['status' => ['Transition not allowed from current status.']],
                422
            );
        }

        $meta = collect($this->transitions->forTicket($ticket, Auth::user()))
            ->firstWhere('status', $toStatus);

        if ($meta && ($meta['requires_reason'] ?? false) && blank($data['reason'] ?? null)) {
            return ApiResponse::error(
                "A reason is required to transition to [{$toStatus}].",
                ['reason' => ['Reason is required for this transition.']],
                422
            );
        }

        if ($meta && ! empty($meta['permission']) && ! Auth::user()->can($meta['permission']) && ! Auth::user()->isSuperAdmin()) {
            return ApiResponse::error(
                "You lack permission [{$meta['permission']}] for this transition.",
                [],
                403
            );
        }

        try {
            $ticket = $this->tickets->transition(
                $ticket,
                $toStatus,
                Auth::user(),
                $data['reason'] ?? null,
                'api',
                $data['comment'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        $payload = (new TicketResource($ticket))->resolve();
        $payload['allowed_transitions'] = $this->transitions->forTicket($ticket, Auth::user());

        return ApiResponse::success($payload);
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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Ticket>  $query
     */
    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('type_code'), fn ($q) => $q->where('type_code', $request->string('type_code')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('assigned_department_id', $request->integer('department_id')))
            ->when($request->filled('team_id'), fn ($q) => $q->where('assigned_team_id', $request->integer('team_id')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('primary_assignee_id', $request->integer('assignee_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('major_incident_id'), fn ($q) => $q->where('major_incident_id', $request->integer('major_incident_id')))
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('created_from')))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('created_to')))
            ->when($request->filled('updated_from'), fn ($q) => $q->whereDate('updated_at', '>=', $request->date('updated_from')))
            ->when($request->filled('updated_to'), fn ($q) => $q->whereDate('updated_at', '<=', $request->date('updated_to')));

        if ($request->boolean('unassigned')) {
            $query->whereNull('primary_assignee_id');
        }

        if ($request->boolean('overdue')) {
            $query->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', now())
                        ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELLED, Ticket::STATUS_RESOLVED]);
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('response_due_at')
                        ->where('response_due_at', '<', now())
                        ->whereNull('first_response_at');
                });
            });
        }

        if ($request->filled('sla_state')) {
            $state = (string) $request->string('sla_state');
            $query->whereHas('slaState', function ($q) use ($state) {
                match ($state) {
                    'ok' => $q->where(function ($inner) {
                        $inner->whereIn('response_state', ['running', 'met'])
                            ->whereIn('resolution_state', ['running', 'met'])
                            ->whereNull('paused_at')
                            ->whereNull('breached_at');
                    }),
                    'at_risk' => $q->where(function ($inner) {
                        $inner->whereNull('breached_at')
                            ->whereNull('paused_at')
                            ->where(function ($risk) {
                                $risk->where(function ($r) {
                                    $r->whereNotNull('response_remaining_seconds')
                                        ->where('response_remaining_seconds', '>', 0)
                                        ->where('response_remaining_seconds', '<=', 1800);
                                })->orWhere(function ($r) {
                                    $r->whereNotNull('resolution_remaining_seconds')
                                        ->where('resolution_remaining_seconds', '>', 0)
                                        ->where('resolution_remaining_seconds', '<=', 3600);
                                });
                            });
                    }),
                    'breached' => $q->where(function ($inner) {
                        $inner->whereNotNull('breached_at')
                            ->orWhere('response_state', 'breached')
                            ->orWhere('resolution_state', 'breached');
                    }),
                    'paused' => $q->where(function ($inner) {
                        $inner->whereNotNull('paused_at')
                            ->orWhere('response_state', 'paused')
                            ->orWhere('resolution_state', 'paused');
                    }),
                    default => $q->where(function ($inner) use ($state) {
                        $inner->where('response_state', $state)->orWhere('resolution_state', $state);
                    }),
                };
            });
        }

        $search = $request->input('search', $request->input('q'));
        if (filled($search)) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('customer_number', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('contact_name', 'like', $term)
                            ->orWhere('company_name', 'like', $term)
                            ->orWhere('customer_number', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('mobile', 'like', $term);
                    });
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Ticket>  $query
     */
    private function applySort($query, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-+');
        $allowed = [
            'id', 'created_at', 'updated_at', 'priority', 'status', 'resolution_due_at',
            'response_due_at', 'ticket_number',
        ];

        if (! in_array($column, $allowed, true)) {
            $column = 'id';
            $direction = 'desc';
        }

        $query->orderBy($column, $direction);
    }
}
