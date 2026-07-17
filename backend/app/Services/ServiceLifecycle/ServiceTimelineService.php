<?php

namespace App\Services\ServiceLifecycle;

use App\Models\Services\Service;
use App\Models\Services\ServiceActivation;
use App\Models\Services\ServiceCancellation;
use App\Models\Services\ServiceChangeRequest;
use App\Models\Services\ServiceFinanceHold;
use App\Models\Services\ServiceRelocation;
use App\Models\Services\ServiceRenewal;
use App\Models\Services\ServiceStatusTransition;
use App\Models\Services\ServiceSuspension;
use Illuminate\Support\Collection;

class ServiceTimelineService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forService(Service $service): array
    {
        $events = collect();

        $events = $events->merge(
            ServiceStatusTransition::query()->where('service_id', $service->id)->get()->map(fn ($t) => [
                'type' => 'status_transition',
                'at' => $t->created_at,
                'data' => $t->toArray(),
            ])
        )->merge(
            ServiceActivation::query()->where('service_id', $service->id)->get()->map(fn ($a) => [
                'type' => 'activation',
                'at' => $a->activated_at,
                'data' => $a->toArray(),
            ])
        )->merge(
            ServiceSuspension::query()->where('service_id', $service->id)->get()->map(fn ($s) => [
                'type' => 'suspension',
                'at' => $s->effective_at,
                'data' => $s->toArray(),
            ])
        )->merge(
            ServiceCancellation::query()->where('service_id', $service->id)->get()->map(fn ($c) => [
                'type' => 'cancellation',
                'at' => $c->cancelled_at ?? $c->created_at,
                'data' => $c->toArray(),
            ])
        )->merge(
            ServiceChangeRequest::query()->where('service_id', $service->id)->get()->map(fn ($c) => [
                'type' => 'change_request',
                'at' => $c->created_at,
                'data' => $c->toArray(),
            ])
        )->merge(
            ServiceRelocation::query()->where('service_id', $service->id)->get()->map(fn ($r) => [
                'type' => 'relocation',
                'at' => $r->created_at,
                'data' => $r->toArray(),
            ])
        )->merge(
            ServiceRenewal::query()->where('service_id', $service->id)->get()->map(fn ($r) => [
                'type' => 'renewal',
                'at' => $r->created_at,
                'data' => $r->toArray(),
            ])
        )->merge(
            ServiceFinanceHold::query()->where('service_id', $service->id)->get()->map(fn ($h) => [
                'type' => 'finance_hold',
                'at' => $h->effective_at,
                'data' => $h->toArray(),
            ])
        );

        return $events
            ->sortByDesc(fn ($e) => $e['at']?->timestamp ?? 0)
            ->values()
            ->all();
    }
}
