<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CollectionRoute;
use App\Services\Routes\RouteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RouteController extends Controller
{
    public function __construct(protected RouteService $routes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CollectionRoute::class);

        $page = CollectionRoute::query()
            ->with(['collector.user:id,name', 'stops'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('collector_id'), fn ($q) => $q->where('collector_id', $request->integer('collector_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('route_date')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($page->items(), [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $route = CollectionRoute::with(['stops.customer', 'collector.user'])->findOrFail($id);
        $this->authorize('view', $route);

        return ApiResponse::success($route);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CollectionRoute::class);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'collector_id' => ['required', 'integer', 'exists:collectors,id'],
            'name' => ['required', 'string', 'max:255'],
            'route_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'stops' => ['nullable', 'array'],
            'stops.*.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'stops.*.assignment_id' => ['nullable', 'integer', 'exists:customer_assignments,id'],
            'stops.*.sequence' => ['nullable', 'integer', 'min:1'],
            'stops.*.notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->routes->create($data, Auth::user()), null, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('update', $route);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'route_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'collector_id' => ['sometimes', 'integer', 'exists:collectors,id'],
            'stops' => ['nullable', 'array'],
            'stops.*.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'stops.*.assignment_id' => ['nullable', 'integer'],
            'stops.*.sequence' => ['nullable', 'integer', 'min:1'],
            'stops.*.notes' => ['nullable', 'string'],
        ]);

        return ApiResponse::success($this->routes->update($route, $data));
    }

    public function destroy(int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('update', $route);

        if ($route->status !== CollectionRoute::STATUS_DRAFT) {
            return ApiResponse::error('Only draft routes can be deleted.', [], 422);
        }

        $route->stops()->delete();
        $route->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function publish(int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('manage', $route);

        return ApiResponse::success($this->routes->publish($route, Auth::user()));
    }

    public function start(int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('view', $route);

        return ApiResponse::success($this->routes->start($route, Auth::user()));
    }

    public function complete(int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('view', $route);

        return ApiResponse::success($this->routes->complete($route, Auth::user()));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('manage', $route);

        $data = $request->validate(['reason' => ['nullable', 'string']]);

        return ApiResponse::success($this->routes->cancel($route, Auth::user(), $data['reason'] ?? null));
    }

    public function reorderStops(Request $request, int $id): JsonResponse
    {
        $route = CollectionRoute::findOrFail($id);
        $this->authorize('manage', $route);

        $data = $request->validate([
            'stops' => ['required', 'array', 'min:1'],
            'stops.*.id' => ['required', 'integer', 'exists:collection_route_stops,id'],
            'stops.*.sequence' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success($this->routes->reorderStops($route, $data['stops']));
    }
}
