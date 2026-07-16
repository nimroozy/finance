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
            $builder->whereExists(function ($q) use ($collectorId, $table) {
                $q->select(DB::raw(1))
                    ->from('customer_assignments')
                    ->whereColumn('customer_assignments.customer_id', "{$table}.id")
                    ->where('customer_assignments.collector_id', $collectorId)
                    ->where('customer_assignments.is_active', true)
                    ->whereNull('customer_assignments.deleted_at');
            });
        }

        if ($table === 'invoices') {
            $builder->whereExists(function ($q) use ($collectorId, $table) {
                $q->select(DB::raw(1))
                    ->from('customer_assignments')
                    ->whereColumn('customer_assignments.customer_id', "{$table}.customer_id")
                    ->where('customer_assignments.collector_id', $collectorId)
                    ->where('customer_assignments.is_active', true)
                    ->whereNull('customer_assignments.deleted_at');
            });
        }
    }
}
