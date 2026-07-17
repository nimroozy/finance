<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\OperationsReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsReportController extends Controller
{
    public function __construct(private OperationsReportService $reports) {}

    public function tickets(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->can('tickets.view'), 403);

        return $this->reports->ticketsCsv($this->branchIds($request), $request->only(['status', 'from', 'to']));
    }

    public function tasks(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->can('tasks.view'), 403);

        return $this->reports->tasksCsv($this->branchIds($request), $request->only(['status']));
    }

    /** @return list<int>|null */
    private function branchIds(Request $request): ?array
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->isCentralFinanceAdmin()) {
            return $request->filled('branch_id') ? [$request->integer('branch_id')] : null;
        }

        return $user->branchIds();
    }
}
