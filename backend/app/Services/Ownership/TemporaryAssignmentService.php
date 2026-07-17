<?php

namespace App\Services\Ownership;

use App\Events\BusinessNotificationRequested;
use App\Models\Collector;
use App\Models\Customer;
use App\Models\TemporaryAssignmentHistory;
use App\Models\TemporaryCollectionAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TemporaryAssignmentService
{
    public function __construct(
        private OwnershipResolutionService $resolver,
        private CustomerWorkQueueService $queues,
        private AuditLogger $audit,
    ) {}

    public function create(
        Customer $customer,
        Collector $temporaryCollector,
        User $actor,
        string $startDate,
        string $endDate,
        ?string $reason = null,
        string $priority = 'normal',
        ?User $approver = null,
    ): TemporaryCollectionAssignment {
        if ($customer->is_unmapped || $customer->branch_id === null) {
            throw new InvalidArgumentException('Unmapped customers cannot receive temporary assignments.');
        }
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('Temporary assignment end date must be on or after start date.');
        }
        $this->resolver->assertCollectorEligible($temporaryCollector, (int) $customer->branch_id);

        return DB::transaction(function () use ($customer, $temporaryCollector, $actor, $startDate, $endDate, $reason, $priority, $approver) {
            $overlap = TemporaryCollectionAssignment::query()
                ->where('customer_id', $customer->id)
                ->where('is_active', true)
                ->where('status', TemporaryCollectionAssignment::STATUS_ACTIVE)
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw new InvalidArgumentException('Overlapping temporary assignment exists.');
            }

            $permanent = $this->resolver->resolve($customer);
            $row = TemporaryCollectionAssignment::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'temporary_collector_id' => $temporaryCollector->id,
                'permanent_collector_id' => $permanent['source'] === 'permanent' ? $permanent['collector_id'] : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'priority' => $priority,
                'status' => TemporaryCollectionAssignment::STATUS_ACTIVE,
                'created_by' => $actor->id,
                'approved_by' => $approver?->id ?? $actor->id,
                'is_active' => true,
            ]);

            TemporaryAssignmentHistory::query()->create([
                'temporary_assignment_id' => $row->id,
                'customer_id' => $customer->id,
                'actor_id' => $actor->id,
                'event' => 'created',
                'after' => $row->toArray(),
                'notes' => $reason,
            ]);
            $this->audit->log('ownership.temporary_created', $row, null, $row->toArray(), $customer->branch_id, $reason);
            $this->queues->recalculate($customer);
            BusinessNotificationRequested::dispatch('temporary_assignment_created', [
                'branch_id' => $customer->branch_id,
                'customer_id' => $customer->id,
                'user_id' => $temporaryCollector->user_id,
                'phone' => $temporaryCollector->user?->phone,
                'title' => 'Temporary assignment created',
                'assignment_id' => $row->id,
                'end_date' => $endDate,
            ]);

            return $row;
        });
    }

    public function cancel(TemporaryCollectionAssignment $assignment, User $actor, ?string $reason = null): TemporaryCollectionAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $reason) {
            $before = $assignment->toArray();
            $assignment->update([
                'status' => TemporaryCollectionAssignment::STATUS_CANCELLED,
                'is_active' => false,
                'cancelled_at' => now(),
            ]);
            TemporaryAssignmentHistory::query()->create([
                'temporary_assignment_id' => $assignment->id,
                'customer_id' => $assignment->customer_id,
                'actor_id' => $actor->id,
                'event' => 'cancelled',
                'before' => $before,
                'after' => $assignment->fresh()->toArray(),
                'notes' => $reason,
            ]);
            $this->audit->log('ownership.temporary_cancelled', $assignment, $before, $assignment->fresh()->toArray(), $assignment->branch_id, $reason);
            $customer = Customer::withoutGlobalScopes()->find($assignment->customer_id);
            if ($customer) {
                $this->queues->recalculate($customer);
            }

            return $assignment->fresh();
        });
    }

    public function expireDue(?string $onDate = null): int
    {
        $on = $onDate ? date('Y-m-d', strtotime($onDate)) : now()->toDateString();
        $rows = TemporaryCollectionAssignment::query()
            ->where('is_active', true)
            ->where('status', TemporaryCollectionAssignment::STATUS_ACTIVE)
            ->whereDate('end_date', '<', $on)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            DB::transaction(function () use ($row, &$count) {
                $before = $row->toArray();
                $row->update([
                    'status' => TemporaryCollectionAssignment::STATUS_EXPIRED,
                    'is_active' => false,
                    'expired_at' => now(),
                ]);
                TemporaryAssignmentHistory::query()->create([
                    'temporary_assignment_id' => $row->id,
                    'customer_id' => $row->customer_id,
                    'actor_id' => null,
                    'event' => 'expired',
                    'before' => $before,
                    'after' => $row->fresh()->toArray(),
                ]);
                $this->audit->log('ownership.temporary_expired', $row, $before, $row->fresh()->toArray(), $row->branch_id);
                $customer = Customer::withoutGlobalScopes()->find($row->customer_id);
                if ($customer) {
                    $this->queues->recalculate($customer);
                }
                $count++;
            });
        }

        return $count;
    }
}
