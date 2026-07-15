<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BranchScope implements Scope
{
    /**
     * Non-global roles may only see branches they belong to.
     */
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

        $builder->whereIn($model->getQualifiedKeyName(), $branchIds);
    }
}
