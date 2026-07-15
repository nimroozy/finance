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
    }
}
