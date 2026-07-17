<?php

namespace App\Services\ServiceLifecycle;

use App\Events\Services\ServiceActivated;
use App\Models\Customer;
use App\Models\Inventory\CustomerEquipment;
use App\Models\Services\Service;
use App\Models\Services\ServiceActivation;
use App\Models\Tickets\Installation;
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
     * Evaluate checklist items from persisted evidence (never trust client booleans).
     *
     * @return array{checklist: array<string, bool>, missing: list<string>, evidence: array<string, mixed>}
     */
    public function evaluateChecklist(Service $service): array
    {
        $required = config('service_lifecycle.activation_checklist', []);
        $evidence = $this->gatherEvidence($service);
        $checklist = [];
        foreach ($required as $key) {
            $checklist[$key] = (bool) ($evidence[$key]['ok'] ?? false);
        }
        $missing = array_values(array_keys(array_filter($checklist, fn ($ok) => ! $ok)));

        return [
            'checklist' => $checklist,
            'missing' => $missing,
            'evidence' => $evidence,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function gatherEvidence(Service $service): array
    {
        $customer = $service->relationLoaded('customer')
            ? $service->customer
            : Customer::query()->find($service->customer_id);

        $zohoId = $service->zoho_customer_id ?: $customer?->zoho_contact_id;
        $zohoLinked = filled($zohoId);

        $installation = null;
        if ($service->installation_id) {
            $installation = $service->relationLoaded('installation')
                ? $service->installation
                : Installation::query()->find($service->installation_id);
        }
        $installationCompleted = $installation
            && $installation->status === Installation::STATUS_COMPLETED;

        $equipment = CustomerEquipment::withoutGlobalScopes()
            ->where('service_id', $service->id)
            ->get();
        $equipmentAssigned = $equipment->isNotEmpty();

        // Reconciled = assigned gear exists and none are in an unresolved/conflict state.
        $unreconciled = $equipment->filter(function (CustomerEquipment $eq) {
            $status = strtolower((string) ($eq->status ?? ''));

            return in_array($status, ['missing', 'lost', 'conflict', 'unreconciled', 'pending'], true);
        });
        $inventoryReconciled = $equipmentAssigned && $unreconciled->isEmpty();

        return [
            'zoho_linked' => [
                'ok' => $zohoLinked,
                'zoho_customer_id' => $zohoId,
            ],
            'installation_completed' => [
                'ok' => (bool) $installationCompleted,
                'installation_id' => $service->installation_id,
                'installation_status' => $installation?->status,
            ],
            'inventory_reconciled' => [
                'ok' => $inventoryReconciled,
                'assigned_count' => $equipment->count(),
                'unreconciled_count' => $unreconciled->count(),
            ],
            'equipment_assigned' => [
                'ok' => $equipmentAssigned,
                'assigned_count' => $equipment->count(),
            ],
        ];
    }

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

        $evaluation = $this->evaluateChecklist($service);
        $checklist = $evaluation['checklist'];
        $missing = $evaluation['missing'];

        // Client-supplied checklist flags are ignored for pass/fail — evidence only.
        if ($missing !== [] && empty($data['skip_checklist'])) {
            throw new InvalidArgumentException('Activation checklist incomplete: '.implode(', ', $missing));
        }

        if (config('service_lifecycle.radius.enabled')) {
            throw new InvalidArgumentException('Radius must remain disabled for Stage 10 service lifecycle.');
        }

        return DB::transaction(function () use ($service, $data, $actor, $idempotencyKey, $checklist, $evaluation) {
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

            // Ready for NOC — never auto-flip to online or billing current.
            if ($service->operational_status === Service::OPERATIONAL_NOT_PROVISIONED
                || $service->operational_status === Service::OPERATIONAL_PENDING_INSTALL) {
                $this->lifecycle->transition($service, [
                    'operational' => Service::OPERATIONAL_READY,
                ], $actor, 'pre_activation_ready', 'activation');
                $service->refresh();
            }

            $this->lifecycle->transition($service, [
                'commercial' => Service::COMMERCIAL_ACTIVE,
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
                'checklist' => [
                    'items' => $checklist,
                    'evidence' => $evaluation['evidence'],
                    'skipped' => (bool) ($data['skip_checklist'] ?? false),
                ],
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->tasks->create([
                'branch_id' => $service->branch_id,
                'customer_id' => $service->customer_id,
                'title' => 'Post-activation verification: '.$service->service_number,
                'description' => 'Verify customer service after activation (no Radius automation). NOC must confirm online separately.',
                'type' => 'office',
                'priority' => 'normal',
            ], $actor);

            $this->audit->log('service.activated', $service, null, [
                'idempotency_key' => $idempotencyKey,
                'checklist' => $checklist,
                'operational_status' => $service->operational_status,
                'billing_status' => $service->billing_status,
            ], $service->branch_id);

            ServiceActivated::dispatch($service->id, (int) $service->branch_id);

            return $service->fresh();
        });
    }

    /**
     * Separate NOC confirmation — only path that sets operational online.
     *
     * @param  array<string, mixed>  $data
     */
    public function confirmOnline(Service $service, array $data, ?User $actor = null): Service
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('NOC online confirmation requires a reason.');
        }

        if ($service->commercial_status !== Service::COMMERCIAL_ACTIVE) {
            throw new InvalidArgumentException('Only commercially active services can be confirmed online.');
        }

        if (! in_array($service->operational_status, [
            Service::OPERATIONAL_READY,
            Service::OPERATIONAL_OFFLINE,
            Service::OPERATIONAL_PENDING_INSTALL,
        ], true)) {
            throw new InvalidArgumentException('Service operational status does not allow online confirmation.');
        }

        return DB::transaction(function () use ($service, $data, $actor, $reason) {
            $this->lifecycle->transition($service, [
                'operational' => Service::OPERATIONAL_ONLINE,
            ], $actor, $reason, 'noc_confirm_online', $data['notes'] ?? null);

            $service->refresh();
            $service->updated_by = $actor?->id;
            $service->save();

            $this->audit->log('service.noc_confirmed_online', $service, null, [
                'reason' => $reason,
                'notes' => $data['notes'] ?? null,
            ], $service->branch_id);

            return $service->fresh();
        });
    }
}
