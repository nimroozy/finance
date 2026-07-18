<?php

namespace App\Services\Ownership;

use App\Events\BusinessNotificationRequested;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerCollectorOwnership;
use App\Models\CustomerCollectorOwnershipHistory;
use App\Models\OwnershipConflict;
use App\Models\OwnershipSyncEvent;
use App\Models\OwnershipTransferApproval;
use App\Models\OwnershipTransferRequest;
use App\Models\TemporaryCollectionAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerOwnershipService
{
    public function __construct(
        private OwnershipResolutionService $resolver,
        private CustomerWorkQueueService $queues,
        private AuditLogger $audit,
    ) {}

    public function assignPermanent(
        Customer $customer,
        Collector $collector,
        User $actor,
        string $startDate,
        ?string $reason = null,
        ?string $area = null,
        ?string $routeLabel = null,
    ): CustomerCollectorOwnership {
        if ($customer->is_unmapped || $customer->branch_id === null) {
            throw new InvalidArgumentException('Unmapped customers cannot receive permanent ownership.');
        }

        $this->resolver->assertCollectorEligible($collector, (int) $customer->branch_id);

        return DB::transaction(function () use ($customer, $collector, $actor, $startDate, $reason, $area, $routeLabel) {
            $existing = CustomerCollectorOwnership::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->active()
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('Customer already has an active permanent owner. Transfer instead.');
            }

            $ownership = CustomerCollectorOwnership::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'collector_id' => $collector->id,
                'assigned_by' => $actor->id,
                'start_date' => $startDate,
                'status' => CustomerCollectorOwnership::STATUS_ACTIVE,
                'reason' => $reason,
                'area' => $area,
                'route_label' => $routeLabel,
                'is_active' => true,
            ]);

            $this->history($ownership, $actor, 'assigned', null, $ownership->toArray(), $reason);
            $this->audit->log('ownership.permanent_assigned', $ownership, null, $ownership->toArray(), $customer->branch_id, $reason);
            OwnershipSyncEvent::query()->create([
                'customer_id' => $customer->id,
                'event_type' => 'permanent_assigned',
                'source' => 'api',
                'payload' => ['ownership_id' => $ownership->id, 'collector_id' => $collector->id],
            ]);
            $this->queues->recalculate($customer);
            BusinessNotificationRequested::dispatch('permanent_ownership_assigned', [
                'branch_id' => $customer->branch_id,
                'customer_id' => $customer->id,
                'user_id' => $collector->user_id,
                'phone' => $collector->user?->phone,
                'title' => 'Permanent ownership assigned',
                'ownership_id' => $ownership->id,
            ]);

            return $ownership;
        });
    }

    public function transfer(
        Customer $customer,
        Collector $toCollector,
        User $actor,
        string $effectiveDate,
        string $reason,
        bool $requireApproval = false,
    ): CustomerCollectorOwnership {
        if ($customer->is_unmapped || $customer->branch_id === null) {
            throw new InvalidArgumentException('Unmapped customers cannot transfer ownership.');
        }
        $this->resolver->assertCollectorEligible($toCollector, (int) $customer->branch_id);

        return DB::transaction(function () use ($customer, $toCollector, $actor, $effectiveDate, $reason, $requireApproval) {
            $current = CustomerCollectorOwnership::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->active()
                ->first();

            $request = OwnershipTransferRequest::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'from_collector_id' => $current?->collector_id,
                'to_collector_id' => $toCollector->id,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'status' => $requireApproval ? 'pending' : 'applied',
                'requested_by' => $actor->id,
                'applied_at' => $requireApproval ? null : now(),
            ]);

            if ($requireApproval) {
                return $current ?? throw new InvalidArgumentException('No active ownership to transfer.');
            }

            OwnershipTransferApproval::query()->create([
                'transfer_request_id' => $request->id,
                'approved_by' => $actor->id,
                'decision' => 'approved',
                'notes' => 'auto-applied',
            ]);

            if ($current) {
                $before = $current->toArray();
                $current->update([
                    'status' => CustomerCollectorOwnership::STATUS_TRANSFERRED,
                    'is_active' => false,
                    'end_date' => $effectiveDate,
                ]);
                $this->history($current, $actor, 'transferred_out', $before, $current->fresh()->toArray(), $reason);
            }

            $ownership = CustomerCollectorOwnership::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'collector_id' => $toCollector->id,
                'assigned_by' => $actor->id,
                'start_date' => $effectiveDate,
                'status' => CustomerCollectorOwnership::STATUS_ACTIVE,
                'reason' => $reason,
                'area' => $current?->area,
                'route_label' => $current?->route_label,
                'is_active' => true,
            ]);

            $this->history($ownership, $actor, 'transferred_in', null, $ownership->toArray(), $reason);
            $this->audit->log('ownership.permanent_transferred', $ownership, [
                'from' => $current?->collector_id,
            ], $ownership->toArray(), $customer->branch_id, $reason);
            $this->queues->recalculate($customer);

            return $ownership;
        });
    }

    public function end(CustomerCollectorOwnership $ownership, User $actor, string $reason, string $status = CustomerCollectorOwnership::STATUS_ENDED): CustomerCollectorOwnership
    {
        return DB::transaction(function () use ($ownership, $actor, $reason, $status) {
            $before = $ownership->toArray();
            $ownership->update([
                'status' => $status,
                'is_active' => false,
                'end_date' => now()->toDateString(),
            ]);
            $this->history($ownership, $actor, 'ended', $before, $ownership->fresh()->toArray(), $reason);
            $this->audit->log('ownership.permanent_ended', $ownership, $before, $ownership->fresh()->toArray(), $ownership->branch_id, $reason);
            $this->queues->recalculate(\App\Models\Customer::withoutGlobalScopes()->findOrFail($ownership->customer_id));

            return $ownership->fresh();
        });
    }

    public function suspend(CustomerCollectorOwnership $ownership, User $actor, string $reason): CustomerCollectorOwnership
    {
        return $this->end($ownership, $actor, $reason, CustomerCollectorOwnership::STATUS_SUSPENDED);
    }

    /**
     * @param  array<int, int>  $customerIds
     * @return array{assigned:int,failed:array<int,array{customer_id:int,error:string}>}
     */
    public function bulkAssign(array $customerIds, Collector $collector, User $actor, string $startDate, ?string $reason = null): array
    {
        $assigned = 0;
        $failed = [];
        foreach ($customerIds as $customerId) {
            try {
                $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);
                $this->assignPermanent($customer, $collector, $actor, $startDate, $reason);
                $assigned++;
            } catch (\Throwable $e) {
                $failed[] = ['customer_id' => (int) $customerId, 'error' => $e->getMessage()];
            }
        }

        return ['assigned' => $assigned, 'failed' => $failed];
    }

    public function flagConflict(Customer $customer, string $type, array $details = []): OwnershipConflict
    {
        return OwnershipConflict::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $customer->branch_id,
            'conflict_type' => $type,
            'status' => 'open',
            'details' => $details,
        ]);
    }

    private function history(
        CustomerCollectorOwnership $ownership,
        User $actor,
        string $event,
        ?array $before,
        ?array $after,
        ?string $notes,
    ): void {
        CustomerCollectorOwnershipHistory::query()->create([
            'ownership_id' => $ownership->id,
            'customer_id' => $ownership->customer_id,
            'branch_id' => $ownership->branch_id,
            'collector_id' => $ownership->collector_id,
            'actor_id' => $actor->id,
            'event' => $event,
            'before' => $before,
            'after' => $after,
            'notes' => $notes,
        ]);
    }
}
