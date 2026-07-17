<?php

namespace App\Services\Search;

use App\Models\Installation;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationalSearchService
{
    /**
     * Branch-scoped operational search across tickets, tasks, installations.
     *
     * @param  list<int>  $branchIds
     * @return array{tickets: Collection, tasks: Collection, installations: Collection}
     */
    public function search(string $query, array $branchIds, User $actor, int $limit = 25): array
    {
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        if ($branchIds === []) {
            return ['tickets' => collect(), 'tasks' => collect(), 'installations' => collect()];
        }

        if (! $actor->isSuperAdmin() && ! $actor->isCentralFinanceAdmin()) {
            $branchIds = array_values(array_intersect($branchIds, $actor->branchIds()));
        }

        $q = trim($query);
        if ($q === '' || $branchIds === []) {
            return ['tickets' => collect(), 'tasks' => collect(), 'installations' => collect()];
        }

        $like = '%'.$q.'%';

        return [
            'tickets' => Ticket::query()
                ->whereIn('branch_id', $branchIds)
                ->where(function ($qq) use ($like, $q) {
                    $qq->where('number', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('description', 'like', $like);
                    if (ctype_digit($q)) {
                        $qq->orWhere('id', (int) $q);
                    }
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            'tasks' => Task::query()
                ->whereIn('branch_id', $branchIds)
                ->where(function ($qq) use ($like, $q) {
                    $qq->where('number', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                    if (ctype_digit($q)) {
                        $qq->orWhere('id', (int) $q);
                    }
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            'installations' => Installation::query()
                ->whereIn('branch_id', $branchIds)
                ->where(function ($qq) use ($like, $q) {
                    $qq->where('number', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('notes', 'like', $like);
                    if (ctype_digit($q)) {
                        $qq->orWhere('id', (int) $q);
                    }
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
        ];
    }
}
