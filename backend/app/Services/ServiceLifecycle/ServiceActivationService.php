<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceActivated;
use App\Models\Services\Service;
use App\Models\Services\ServiceActivation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceActivationService
{
    public function __construct(
        private ServiceLifecycleService $lifecycle,
        private TaskService $tasks,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function activate(Service $service, array $data, ?User $actor = null): Service
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (! $idempotencyKey) {
            throw new InvalidArgumentException('Activation requires idempotency_key.');
        }

        $existing = ServiceActivation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return Service::query()->findOrFail($existing->service_id);
        }

        $checklist = $data['checklist'] ?? [];
        $required = config('service_lifecycle.activation_checklist', []);
        if (is_array($required) && $required !== []) {
            $provided = collect($checklist)->filter()->keys()->all();
            if (array_is_list($checklist)) {
                $provided = $checklist;
            }
            $missing = array_values(array_diff($required, $provided));
            if ($missing !== [] && empty($data['skip_checklist'])) {
                throw new InvalidArgumentException('Activation checklist incomplete: '.implode(', ', $missing));
            }
        }

        // No Radius provisioning — lifecycle activation only.
        if (config('service_lifecycle.radius.enabled')) {
            throw new InvalidArgumentException('Radius must remain disabled for Stage 10 service lifecycle.');
        }

        return DB::transaction(function () use ($service, $data, $actor, $idempotencyKey, $checklist) {
            if (! in_array($service->commercial_status, [
                Service::COMMERCIAL_DRAFT,
                Service::COMMERCIAL_PENDING_ACTIVATION,
            ], true)) {
                throw new InvalidArgumentException('Only draft/pending_activation services can be activated.');
            }

            if ($service->commercial_status === Service::COMMERCIAL_DRAFT) {
                $this->lifecycle->transition($service, [
                    'commercial' => Service::COMMERCIAL_PENDING_ACTIVATION,
                ], $actor, 'pre_activation', 'activation');
                $service->refresh();
            }

            // Operational path: not_provisioned → ready → online (no Radius).
            if ($service->operational_status === Service::OPERATIONAL_NOT_PROVISIONED) {
                $this->lifecycle->transition($service, [
                    'operational' => Service::OPERATIONAL_READY,
                ], $actor, 'pre_activation_ready', 'activation');
                $service->refresh();
            }
            if (in_array($service->operational_status, [
                Service::OPERATIONAL_PENDING_INSTALL,
                Service::OPERATIONAL_READY,
                Service::OPERATIONAL_OFFLINE,
            ], true)) {
                $this->lifecycle->transition($service, [
                    'operational' => Service::OPERATIONAL_ONLINE,
                ], $actor, 'activation_online', 'activation');
                $service->refresh();
            }

            $this->lifecycle->transition($service, [
                'commercial' => Service::COMMERCIAL_ACTIVE,
                'billing' => Service::BILLING_CURRENT,
            ], $actor, $data['reason'] ?? 'activation', 'activation', $data['notes'] ?? null);

            $service->refresh();
            $service->activation_date = now();
            $service->start_date = $service->start_date ?? now()->toDateString();
            $service->updated_by = $actor?->id;
            $service->save();

            ServiceActivation::query()->create([
                'service_id' => $service->id,
                'activated_by' => $actor?->id,
                'activated_at' => now(),
                'checklist' => $checklist,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->tasks->create([
                'branch_id' => $service->branch_id,
                'customer_id' => $service->customer_id,
                'title' => 'Post-activation verification: '.$service->service_number,
                'description' => 'Verify customer service after activation (no Radius automation).',
                'type' => 'office',
                'priority' => 'normal',
            ], $actor);

            $this->audit->log('service.activated', $service, null, [
                'idempotency_key' => $idempotencyKey,
            ], $service->branch_id);

            ServiceActivated::dispatch($service->id, (int) $service->branch_id);

            return $service->fresh();
        });
    }
}
