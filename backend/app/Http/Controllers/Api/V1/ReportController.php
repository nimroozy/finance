<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\AssignmentReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected AssignmentReportService $reports) {}

    public function assignmentsByCollector(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()->can('reports.assignments'), 403);

        $rows = $this->reports->assignmentsByCollector($request->integer('branch_id') ?: null);

        if ((string) $request->query('export') === 'csv') {
            return $this->reports->toCsv($rows, ['collector_id', 'status', 'total', 'total_outstanding'], fn ($r) => [
                $r->collector_id, $r->status, $r->total, $r->total_outstanding,
            ]);
        }

        return ApiResponse::success($rows);
    }

    public function visitsByOutcome(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()->can('reports.visits'), 403);

        $rows = $this->reports->visitsByOutcome($request->integer('branch_id') ?: null);

        if ((string) $request->query('export') === 'csv') {
            return $this->reports->toCsv($rows, ['outcome', 'total'], fn ($r) => [$r->outcome, $r->total]);
        }

        return ApiResponse::success($rows);
    }

    public function overduePromises(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()->can('reports.promises'), 403);

        $rows = $this->reports->overduePromises($request->integer('branch_id') ?: null);

        if ((string) $request->query('export') === 'csv') {
            return $this->reports->toCsv($rows, ['id', 'customer_id', 'amount', 'promised_date', 'status'], fn ($r) => [
                $r->id, $r->customer_id, $r->amount, $r->promised_date?->toDateString(), $r->status,
            ]);
        }

        return ApiResponse::success($rows);
    }

    public function unassignedDebtors(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()->can('reports.assignments'), 403);

        $rows = $this->reports->unassignedDebtors($request->integer('branch_id') ?: null);

        if ((string) $request->query('export') === 'csv') {
            return $this->reports->toCsv($rows, ['id', 'contact_name', 'outstanding_receivable', 'branch_id'], fn ($r) => [
                $r->id, $r->contact_name, $r->outstanding_receivable, $r->branch_id,
            ]);
        }

        return ApiResponse::success($rows);
    }

    public function gpsMismatch(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless($request->user()->can('reports.visits'), 403);

        $rows = $this->reports->gpsMismatch($request->integer('branch_id') ?: null);

        if ((string) $request->query('export') === 'csv') {
            return $this->reports->toCsv($rows, ['id', 'customer_id', 'distance_meters', 'gps_risk_level', 'visited_at'], fn ($r) => [
                $r->id, $r->customer_id, $r->distance_meters, $r->gps_risk_level, $r->visited_at,
            ]);
        }

        return ApiResponse::success($rows);
    }

    public function paymentsSummary(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->can('payments.export'), 403);

        $summary = app(\App\Services\Payments\PaymentReconciliationService::class)->paymentsSummary(
            $request->integer('branch_id') ?: null,
            $request->query('from'),
            $request->query('to'),
        );

        return ApiResponse::success($summary);
    }

    public function paymentsSyncFailures(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->can('payments.retry_sync'), 403);

        $rows = \App\Models\Payment::query()
            ->with(['customer:id,contact_name', 'method'])
            ->where('zoho_sync_status', \App\Models\Payment::ZOHO_FAILED)
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return ApiResponse::success($rows);
    }
}
