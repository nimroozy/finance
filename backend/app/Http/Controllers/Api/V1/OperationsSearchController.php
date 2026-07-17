<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\OperationalSearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperationsSearchController extends Controller
{
    /** @var list<string> */
    public const SEARCH_PERMISSIONS = [
        'tickets.view',
        'tasks.view',
        'installations.view',
        'customers.view',
        'services.view',
        'crm.leads.view',
        'payments.view',
        'inventory.products.view',
        'inventory.equipment.view',
        'inventory.transfers.view',
        'inventory.sites.view',
        'inventory.towers.view',
        'users.view',
    ];

    public function __construct(private OperationalSearchService $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->canAny(self::SEARCH_PERMISSIONS), 403);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:1'],
            'branch_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            $branchIds = $request->filled('branch_id')
                ? [$request->integer('branch_id')]
                : ($user->branchIds() ?: []);
        } else {
            $branchIds = $user->branchIds();
        }

        if ($request->filled('branch_id') && ! ($user->isSuperAdmin() || $user->isCentralFinanceAdmin())) {
            $branchIds = array_values(array_intersect($branchIds, [$request->integer('branch_id')]));
        }

        if ($branchIds === [] && ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) && ! $request->filled('branch_id')) {
            $branchIds = $user->branchIds();
        }

        $results = $this->search->search(
            $data['q'],
            $branchIds,
            $user,
            (int) ($data['limit'] ?? 25),
        );

        return ApiResponse::success($results);
    }
}
