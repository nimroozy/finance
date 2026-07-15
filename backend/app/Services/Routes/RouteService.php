<?php

namespace App\Services\Routes;

use App\Models\CollectionRoute;
use App\Models\CollectionRouteStop;
use App\Models\CustomerAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CollectionRoute
    {
        return DB::transaction(function () use ($data, $actor) {
            $route = CollectionRoute::create([
                'branch_id' => $data['branch_id'],
                'collector_id' => $data['collector_id'],
                'created_by' => $actor->id,
                'name' => $data['name'],
                'route_date' => $data['route_date'],
                'status' => CollectionRoute::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncStops($route, $data['stops'] ?? []);
            $this->audit->log('route.created', $route, null, $route->toArray(), $route->branch_id);

            return $route->fresh(['stops.customer', 'collector.user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CollectionRoute $route, array $data): CollectionRoute
    {
        if ($route->status !== CollectionRoute::STATUS_DRAFT) {
            throw ValidationException::withMessages(['route' => ['Only draft routes can be updated.']]);
        }

        return DB::transaction(function () use ($route, $data) {
            $route->update(collect($data)->only(['name', 'route_date', 'notes', 'collector_id'])->filter(fn ($v) => $v !== null)->all());

            if (array_key_exists('stops', $data)) {
                $route->stops()->delete();
                $this->syncStops($route, $data['stops']);
            }

            return $route->fresh(['stops.customer', 'collector.user']);
        });
    }

    public function publish(CollectionRoute $route, User $actor): CollectionRoute
    {
        if ($route->status !== CollectionRoute::STATUS_DRAFT) {
            throw ValidationException::withMessages(['route' => ['Only draft routes can be published.']]);
        }

        if ($route->stops()->count() === 0) {
            throw ValidationException::withMessages(['stops' => ['Route must have at least one stop.']]);
        }

        $route->update([
            'status' => CollectionRoute::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->audit->log('route.published', $route, null, $route->toArray(), $route->branch_id);

        return $route->fresh(['stops']);
    }

    public function start(CollectionRoute $route, User $actor): CollectionRoute
    {
        if (! in_array($route->status, [CollectionRoute::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages(['route' => ['Only published routes can be started.']]);
        }

        $route->update([
            'status' => CollectionRoute::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->audit->log('route.started', $route, null, $route->toArray(), $route->branch_id);

        return $route->fresh();
    }

    public function complete(CollectionRoute $route, User $actor): CollectionRoute
    {
        if ($route->status !== CollectionRoute::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['route' => ['Only in-progress routes can be completed.']]);
        }

        $route->update([
            'status' => CollectionRoute::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->audit->log('route.completed', $route, null, $route->toArray(), $route->branch_id);

        return $route->fresh();
    }

    public function cancel(CollectionRoute $route, User $actor, ?string $reason = null): CollectionRoute
    {
        if (in_array($route->status, [CollectionRoute::STATUS_COMPLETED, CollectionRoute::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['route' => ['Route cannot be cancelled.']]);
        }

        $route->update([
            'status' => CollectionRoute::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        $this->audit->log('route.cancelled', $route, null, $route->toArray(), $route->branch_id, $reason);

        return $route->fresh();
    }

    /**
     * @param  list<array{id?: int, sequence: int}>  $ordered
     */
    public function reorderStops(CollectionRoute $route, array $ordered): CollectionRoute
    {
        if (! in_array($route->status, [CollectionRoute::STATUS_DRAFT, CollectionRoute::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages(['route' => ['Stops can only be reordered on draft/published routes.']]);
        }

        DB::transaction(function () use ($route, $ordered) {
            // Temporary sequences to avoid unique collisions.
            foreach ($ordered as $i => $row) {
                CollectionRouteStop::where('route_id', $route->id)
                    ->where('id', $row['id'])
                    ->update(['sequence' => 10000 + $i]);
            }

            foreach ($ordered as $row) {
                CollectionRouteStop::where('route_id', $route->id)
                    ->where('id', $row['id'])
                    ->update(['sequence' => (int) $row['sequence']]);
            }
        });

        return $route->fresh(['stops']);
    }

    /**
     * @param  list<array{customer_id: int, assignment_id?: int|null, notes?: string|null, sequence?: int}>  $stops
     */
    protected function syncStops(CollectionRoute $route, array $stops): void
    {
        foreach (array_values($stops) as $index => $stop) {
            $assignmentId = $stop['assignment_id'] ?? null;
            if (! $assignmentId) {
                $assignmentId = CustomerAssignment::withoutGlobalScopes()
                    ->where('customer_id', $stop['customer_id'])
                    ->where('collector_id', $route->collector_id)
                    ->where('is_active', true)
                    ->value('id');
            }

            CollectionRouteStop::create([
                'route_id' => $route->id,
                'customer_id' => $stop['customer_id'],
                'assignment_id' => $assignmentId,
                'sequence' => $stop['sequence'] ?? ($index + 1),
                'status' => CollectionRouteStop::STATUS_PENDING,
                'notes' => $stop['notes'] ?? null,
            ]);
        }
    }
}
