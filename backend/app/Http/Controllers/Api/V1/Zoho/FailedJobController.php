<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FailedJobController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $jobs = DB::table('failed_jobs')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $items = collect($jobs->items())->map(fn ($job) => $this->describe($job));

        return ApiResponse::success($items, [
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'per_page' => $jobs->perPage(),
            'total' => $jobs->total(),
        ]);
    }

    public function archive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ]);
        $query = DB::table('failed_jobs')->where('failed_at', '<=', now()->subDay());
        if (array_key_exists('ids', $data)) {
            $query->whereIn('id', $data['ids']);
        }
        $rows = $query->orderBy('id')->get();
        $eligible = $rows->filter(fn ($row) => $this->describe($row)['archivable']);
        $rejectedIds = $rows->pluck('id')->diff($eligible->pluck('id'))->values();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'archived_by' => $request->user()->id,
            'archived_ids' => $eligible->pluck('id')->values()->all(),
            'rejected_ids' => $rejectedIds->all(),
            'jobs' => $eligible->map(fn ($row) => $this->describe($row))->values()->all(),
        ];
        $path = 'zoho-reports/failed-jobs-archive-'.now()->format('Ymd-His-u').'.json';
        Storage::disk('zoho_reports')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        DB::transaction(fn () => DB::table('failed_jobs')->whereIn('id', $eligible->pluck('id'))->delete());
        $this->audit->log('zoho.failed_jobs.archived', null, null, [
            'ids' => $report['archived_ids'],
            'report_path' => $path,
        ]);

        return ApiResponse::success([
            'archived_count' => $eligible->count(),
            'archived_ids' => $report['archived_ids'],
            'rejected_ids' => $report['rejected_ids'],
            'report_path' => $path,
        ]);
    }

    private function describe(object $job): array
    {
        $payload = json_decode((string) $job->payload, true) ?: [];
        $class = (string) ($payload['displayName'] ?? $payload['data']['commandName'] ?? $payload['job'] ?? 'unknown');
        $haystack = strtolower($class.' '.(string) $job->payload);
        $isPayment = str_contains($haystack, 'payment');
        $allowed = preg_match('/(?:^|\\\\)(?:SyncZoho[^\\\\]*|Refresh[^\\\\]*|Retry[^\\\\]*)$/', $class) === 1;
        $failedAt = $job->failed_at ? Carbon::parse($job->failed_at) : null;

        return [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'job_class' => $class,
            'classification' => $isPayment ? 'financial_protected' : ($allowed ? 'zoho_maintenance' : 'unclassified'),
            'exception' => mb_substr((string) $job->exception, 0, 1000),
            'failed_at' => $failedAt?->toIso8601String(),
            'archivable' => ! $isPayment && $allowed && $failedAt?->lte(now()->subDay()),
        ];
    }
}
