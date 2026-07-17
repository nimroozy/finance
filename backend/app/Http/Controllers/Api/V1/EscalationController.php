<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tickets\Escalation;
use App\Models\Tickets\Ticket;
use App\Services\Sla\EscalationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EscalationController extends Controller
{
    public function __construct(private EscalationService $escalations) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('escalations.view'), 403);

        $user = Auth::user();
        $query = Escalation::query()->with('ticket');

        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereHas('ticket', fn ($q) => $q->whereIn('branch_id', $user->branchIds()));
        }

        $page = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('escalations.manage'), 403);

        $data = $request->validate([
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'level' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string'],
            'rule_code' => ['nullable', 'string'],
        ]);

        $ticket = Ticket::query()->findOrFail($data['ticket_id']);
        $this->authorize('view', $ticket);

        $escalation = $this->escalations->create(
            $ticket,
            $data['rule_code'] ?? 'manual',
            $data['level'] ?? 'l1',
            ['reason' => $data['reason'] ?? 'Manual escalation'],
            Auth::user(),
        );

        return ApiResponse::success($escalation, null, 201);
    }

    public function acknowledge(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('escalations.manage') || Auth::user()->can('escalations.view'), 403);

        $escalation = Escalation::query()->findOrFail($id);

        return ApiResponse::success($this->escalations->acknowledge($escalation, Auth::user()));
    }
}
