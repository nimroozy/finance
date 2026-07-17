<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Tickets\TicketIntakeSuggestion;
use App\Services\Tickets\TicketIntakeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketIntakeController extends Controller
{
    public function __construct(private TicketIntakeService $intake) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('whatsapp.ticket_intake') || Auth::user()->can('tickets.view'), 403);

        $user = Auth::user();
        $query = TicketIntakeSuggestion::query()->with('ticket');

        if (! $user->isSuperAdmin() && ! $user->isCentralFinanceAdmin()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        $page = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page);
    }

    public function createTicket(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('whatsapp.ticket_intake') || Auth::user()->can('tickets.create'), 403);

        $suggestion = TicketIntakeSuggestion::query()->findOrFail($id);

        try {
            $suggestion = $this->intake->createTicketFromSuggestion($suggestion, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'suggestion' => $suggestion,
            'ticket' => $suggestion->ticket_id
                ? new TicketResource($suggestion->ticket()->first())
                : null,
        ]);
    }

    public function dismiss(int $id): JsonResponse
    {
        abort_unless(Auth::user()->can('whatsapp.ticket_intake'), 403);

        $suggestion = TicketIntakeSuggestion::query()->findOrFail($id);

        return ApiResponse::success($this->intake->dismiss($suggestion, Auth::user()));
    }
}
