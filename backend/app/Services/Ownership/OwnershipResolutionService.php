<?php

namespace App\Services\Ownership;

use App\Models\Collector;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\CustomerCollectorOwnership;
use App\Models\TemporaryCollectionAssignment;
use App\Models\User;
use InvalidArgumentException;

class OwnershipResolutionService
{
    /**
     * @return array{collector_id:?int,source:string,ownership:?CustomerCollectorOwnership,temporary:?TemporaryCollectionAssignment,manual:?CustomerAssignment}
     */
    public function resolve(Customer $customer, ?string $onDate = null): array
    {
        $on = $onDate ? date('Y-m-d', strtotime($onDate)) : now()->toDateString();

        $temporary = TemporaryCollectionAssignment::query()
            ->currentlyActive($on)
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->first();

        if ($temporary) {
            return [
                'collector_id' => (int) $temporary->temporary_collector_id,
                'source' => 'temporary',
                'ownership' => null,
                'temporary' => $temporary,
                'manual' => null,
            ];
        }

        $ownership = CustomerCollectorOwnership::query()
            ->active()
            ->where('customer_id', $customer->id)
            ->first();

        if ($ownership) {
            return [
                'collector_id' => (int) $ownership->collector_id,
                'source' => 'permanent',
                'ownership' => $ownership,
                'temporary' => null,
                'manual' => null,
            ];
        }

        $manual = CustomerAssignment::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();

        if ($manual) {
            return [
                'collector_id' => (int) $manual->collector_id,
                'source' => 'manual',
                'ownership' => null,
                'temporary' => null,
                'manual' => $manual,
            ];
        }

        return [
            'collector_id' => null,
            'source' => 'unassigned',
            'ownership' => null,
            'temporary' => null,
            'manual' => null,
        ];
    }

    public function assertCollectorEligible(Collector $collector, int $branchId): void
    {
        if (! $collector->is_active) {
            throw new InvalidArgumentException('Inactive collector cannot receive ownership.');
        }
        $user = $collector->user;
        if (! $user || $user->status !== User::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Suspended or missing user cannot receive ownership.');
        }
        $hasBranch = $user->branches()->where('branches.id', $branchId)->exists();
        if (! $hasBranch) {
            throw new InvalidArgumentException('Collector must have access to the customer branch.');
        }
    }

    public function collectorCanOperate(int $collectorId, Customer $customer): bool
    {
        $resolved = $this->resolve($customer);

        return $resolved['collector_id'] !== null && (int) $resolved['collector_id'] === (int) $collectorId;
    }
}
