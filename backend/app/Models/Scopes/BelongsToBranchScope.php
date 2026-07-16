<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scope models that belong to a branch via branch_id (customers, invoices, etc.).
 * Non-global roles only see their branches; unmapped rows are hidden from them.
 * Collectors are further restricted to customers with an active assignment to them.
 */
class BelongsToBranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return;
        }

        $branchIds = DB::table('branch_user')
            ->where('user_id', $user->id)
            ->pluck('branch_id');

        $table = $model->getTable();

        $builder->whereIn("{$table}.branch_id", $branchIds);

        if (in_array('is_unmapped', $model->getFillable(), true)) {
            $builder->where("{$table}.is_unmapped", false);
        }

        if (! $user->isCollectorOnly()) {
            return;
        }

        $collectorId = $user->collector?->id;

        if ($collectorId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($table === 'customers') {
            $builder->where(function ($outer) use ($collectorId, $table) {
                $outer->whereExists(function ($q) use ($collectorId, $table) {
                    $q->select(DB::raw(1))
                        ->from('customer_assignments')
                        ->whereColumn('customer_assignments.customer_id', "{$table}.id")
                        ->where('customer_assignments.collector_id', $collectorId)
                        ->where('customer_assignments.is_active', true)
                        ->whereNull('customer_assignments.deleted_at');
                })->orWhereExists(function ($q) use ($collectorId, $table) {
                    $q->select(DB::raw(1))
                        ->from('customer_collector_ownerships')
                        ->whereColumn('customer_collector_ownerships.customer_id', "{$table}.id")
                        ->where('customer_collector_ownerships.collector_id', $collectorId)
                        ->where('customer_collector_ownerships.is_active', true)
                        ->where('customer_collector_ownerships.status', 'active');
                })->orWhereExists(function ($q) use ($collectorId, $table) {
                    $today = now()->toDateString();
                    $q->select(DB::raw(1))
                        ->from('temporary_collection_assignments')
                        ->whereColumn('temporary_collection_assignments.customer_id', "{$table}.id")
                        ->where('temporary_collection_assignments.temporary_collector_id', $collectorId)
                        ->where('temporary_collection_assignments.is_active', true)
                        ->where('temporary_collection_assignments.status', 'active')
                        ->whereDate('temporary_collection_assignments.start_date', '<=', $today)
                        ->whereDate('temporary_collection_assignments.end_date', '>=', $today);
                });
            });
        }

        if ($table === 'invoices') {
            $builder->where(function ($outer) use ($collectorId, $table) {
                $outer->whereExists(function ($q) use ($collectorId, $table) {
                    $q->select(DB::raw(1))
                        ->from('customer_assignments')
                        ->whereColumn('customer_assignments.customer_id', "{$table}.customer_id")
                        ->where('customer_assignments.collector_id', $collectorId)
                        ->where('customer_assignments.is_active', true)
                        ->whereNull('customer_assignments.deleted_at');
                })->orWhereExists(function ($q) use ($collectorId, $table) {
                    $q->select(DB::raw(1))
                        ->from('customer_collector_ownerships')
                        ->whereColumn('customer_collector_ownerships.customer_id', "{$table}.customer_id")
                        ->where('customer_collector_ownerships.collector_id', $collectorId)
                        ->where('customer_collector_ownerships.is_active', true)
                        ->where('customer_collector_ownerships.status', 'active');
                })->orWhereExists(function ($q) use ($collectorId, $table) {
                    $today = now()->toDateString();
                    $q->select(DB::raw(1))
                        ->from('temporary_collection_assignments')
                        ->whereColumn('temporary_collection_assignments.customer_id', "{$table}.customer_id")
                        ->where('temporary_collection_assignments.temporary_collector_id', $collectorId)
                        ->where('temporary_collection_assignments.is_active', true)
                        ->where('temporary_collection_assignments.status', 'active')
                        ->whereDate('temporary_collection_assignments.start_date', '<=', $today)
                        ->whereDate('temporary_collection_assignments.end_date', '>=', $today);
                });
            });
        }
    }
}
