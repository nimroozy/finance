<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restrict collectors to records tied to their collector profile.
 * Expects a collector_id column on the model table.
 */
class CollectorOwnedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isCollectorOnly()) {
            return;
        }

        $collectorId = $user->collector?->id;

        if ($collectorId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $table = $model->getTable();
        $builder->where("{$table}.collector_id", $collectorId);
    }
}
