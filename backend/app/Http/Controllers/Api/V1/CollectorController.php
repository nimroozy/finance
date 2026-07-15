<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionRoute;
use App\Models\CollectionVisit;
use App\Models\Collector;
use App\Models\CustomerAssignment;
use App\Models\PromiseToPay;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CollectorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collectors.view'), 403);

        $page = Collector::query()
            ->with(['user:id,name,email,status'])
            ->withCount('activeAssignments')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collectors.manage'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:collectors,user_id'],
            'employee_code' => ['nullable', 'string', 'max:50', 'unique:collectors,employee_code'],
            'max_active_assignments' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($data['user_id']);
        if (! $user->hasRole(User::ROLE_COLLECTOR)) {
            $user->assignRole(User::ROLE_COLLECTOR);
        }

        $collector = Collector::create([
            'user_id' => $data['user_id'],
            'employee_code' => $data['employee_code'] ?? null,
            'max_active_assignments' => $data['max_active_assignments'] ?? null,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        return ApiResponse::success($collector->load('user'), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('collectors.manage'), 403);

        $collector = Collector::findOrFail($id);

        $data = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('collectors', 'employee_code')->ignore($collector->id)],
            'max_active_assignments' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $collector->update($data);

        return ApiResponse::success($collector->fresh('user'));
    }

    public function dashboard(): JsonResponse
    {
        $user = Auth::user();
        $collector = $user->collector;

        if (! $collector) {
            return ApiResponse::error('Collector profile not found.', [], 404);
        }

        return ApiResponse::success([
            'collector_id' => $collector->id,
            'active_assignments' => CustomerAssignment::query()
                ->where('collector_id', $collector->id)
                ->where('is_active', true)
                ->count(),
            'visits_today' => CollectionVisit::query()
                ->where('collector_id', $collector->id)
                ->whereDate('visited_at', now()->toDateString())
                ->count(),
            'open_promises' => PromiseToPay::query()
                ->where('collector_id', $collector->id)
                ->whereIn('status', PromiseToPay::OPEN_STATUSES)
                ->count(),
            'routes_today' => CollectionRoute::query()
                ->where('collector_id', $collector->id)
                ->whereDate('route_date', now()->toDateString())
                ->whereIn('status', [CollectionRoute::STATUS_PUBLISHED, CollectionRoute::STATUS_IN_PROGRESS])
                ->with('stops')
                ->get(),
        ]);
    }
}
