<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Jobs\RetryFailedZohoSyncJob;
use App\Jobs\SyncZohoCustomersJob;
use App\Jobs\SyncZohoInvoicesJob;
use App\Jobs\SyncZohoOrganizationStructureJob;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ZohoApiLog;
use App\Models\ZohoBranchAlias;
use App\Models\ZohoBranchMapping;
use App\Models\ZohoCircuitBreaker as ZohoCircuitBreakerModel;
use App\Models\ZohoConnection;
use App\Models\ZohoLocation;
use App\Models\ZohoOrganization;
use App\Models\ZohoPaymentMode;
use App\Models\ZohoReportingTag;
use App\Models\ZohoReportingTagMapping;
use App\Models\ZohoSyncCursor;
use App\Models\ZohoSyncHeartbeat;
use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoCircuitBreaker;
use App\Services\Zoho\ZohoConfig;
use App\Services\Zoho\ZohoConnectionService;
use App\Services\Zoho\ZohoOAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

class ZohoController extends Controller
{
    public function __construct(
        protected ZohoConfig $config,
        protected ZohoOAuthService $oauth,
        protected ZohoConnectionService $connections,
    ) {}

    public function status(): JsonResponse
    {
        $connection = ZohoConnection::current();

        $lastCustomerSync = ZohoSyncJob::query()
            ->where('type', ZohoSyncJob::TYPE_CUSTOMERS)
            ->whereIn('status', [ZohoSyncJob::STATUS_COMPLETED, ZohoSyncJob::STATUS_PARTIALLY_COMPLETED])
            ->latest('finished_at')
            ->first();

        $lastInvoiceSync = ZohoSyncJob::query()
            ->where('type', ZohoSyncJob::TYPE_INVOICES)
            ->whereIn('status', [ZohoSyncJob::STATUS_COMPLETED, ZohoSyncJob::STATUS_PARTIALLY_COMPLETED])
            ->latest('finished_at')
            ->first();

        return ApiResponse::success([
            'connected' => (bool) $connection?->isConnected(),
            'status' => $connection?->status ?? ZohoConnection::STATUS_DISCONNECTED,
            'organization_id' => $connection?->organization_id,
            'organization_name' => $connection?->organization_name,
            'data_center' => $connection?->data_center,
            'accounts_domain' => $connection?->accounts_domain,
            'api_domain' => $connection?->api_domain,
            'token_expires_at' => $connection?->token_expires_at?->toIso8601String(),
            'last_connected_at' => $connection?->last_connected_at?->toIso8601String(),
            'last_error' => $connection?->last_error,
            'scopes' => $connection?->scopes,
            'has_access_token' => filled($connection?->access_token),
            'has_refresh_token' => filled($connection?->refresh_token),
            'last_customer_sync_at' => $lastCustomerSync?->finished_at?->toIso8601String(),
            'last_invoice_sync_at' => $lastInvoiceSync?->finished_at?->toIso8601String(),
        ]);
    }

    public function health(): JsonResponse
    {
        return ApiResponse::success([
            'connection' => ZohoConnection::current(),
            'cursors' => ZohoSyncCursor::query()->get()->keyBy('entity'),
            'circuit_breakers' => ZohoCircuitBreakerModel::query()->get()->keyBy('sync_type'),
            'heartbeats' => ZohoSyncHeartbeat::query()->get()->keyBy('component'),
            'failed_jobs' => ZohoSyncJob::query()->where('status', ZohoSyncJob::STATUS_FAILED)->count(),
            'laravel_failed_jobs' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
            'pending_jobs' => \Illuminate\Support\Facades\DB::table('jobs')->count(),
            'unmapped' => [
                'customers' => Customer::withoutGlobalScopes()->where('is_unmapped', true)->count(),
                'invoices' => Invoice::withoutGlobalScopes()->whereNull('branch_id')->count(),
            ],
            'structure' => [
                'locations' => ZohoLocation::query()->count(),
                'reporting_tags' => ZohoReportingTag::query()->count(),
                'reporting_tag_options' => \App\Models\ZohoReportingTagOption::query()->count(),
                'payment_modes' => ZohoPaymentMode::query()->count(),
            ],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    public function syncStructure(): JsonResponse
    {
        $job = ZohoSyncJob::query()->create([
            'type' => ZohoSyncJob::TYPE_STRUCTURE,
            'status' => ZohoSyncJob::STATUS_PENDING,
            'triggered_by' => Auth::id(),
        ]);
        SyncZohoOrganizationStructureJob::dispatch($job->id);

        return ApiResponse::success(['queued' => true, 'sync_job_id' => $job->id], null, 202);
    }

    public function locations(): JsonResponse
    {
        return ApiResponse::success(ZohoLocation::query()->orderBy('name')->get());
    }

    public function reportingTags(): JsonResponse
    {
        return ApiResponse::success(ZohoReportingTag::query()->with('options')->orderBy('name')->get());
    }

    public function paymentModes(): JsonResponse
    {
        return ApiResponse::success(ZohoPaymentMode::query()->orderBy('name')->get());
    }

    public function dataCenters(): JsonResponse
    {
        $centers = collect($this->config->dataCenters())
            ->map(fn (array $dc, string $code) => [
                'code' => $code,
                'label' => $dc['label'],
                'accounts' => $dc['accounts'],
                'api' => $dc['api'],
            ])
            ->values();

        return ApiResponse::success($centers);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data_center' => ['sometimes', 'string', Rule::in(array_keys($this->config->dataCenters()))],
            'organization_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $connection = $this->connections->updateSettings($data);

        return ApiResponse::success([
            'id' => $connection->id,
            'data_center' => $connection->data_center,
            'accounts_domain' => $connection->accounts_domain,
            'api_domain' => $connection->api_domain,
            'organization_id' => $connection->organization_id,
            'status' => $connection->status,
        ]);
    }

    public function oauthRedirect(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'data_center' => ['sometimes', 'string', Rule::in(array_keys($this->config->dataCenters()))],
            ]);

            $result = $this->oauth->authorizeUrl($data['data_center'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Unable to start Zoho connection. Check ZOHO_CLIENT_ID, ZOHO_CLIENT_SECRET, and ZOHO_REDIRECT_URI, then recreate backend containers.',
                [],
                500
            );
        }

        return ApiResponse::success([
            'authorize_url' => $result['url'],
            'state' => $result['state'],
        ]);
    }

    public function oauthCallback(Request $request): RedirectResponse|JsonResponse
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $successPath = config('zoho.frontend_connected_path', '/en/zoho?connected=1');

        try {
            $request->validate([
                'code' => ['required', 'string'],
                'state' => ['required', 'string'],
            ]);

            $statePayload = $this->oauth->validateState($request->string('state')->toString());
            $this->oauth->exchangeCode(
                $request->string('code')->toString(),
                $statePayload,
                Auth::user()
            );

            return redirect()->away($frontend.$successPath);
        } catch (Throwable $e) {
            $errorUrl = $frontend.'/en/zoho?connected=0&error='.urlencode($e->getMessage());

            if ($request->expectsJson() && ! $request->isMethod('GET')) {
                return ApiResponse::error($e->getMessage(), [], 400);
            }

            return redirect()->away($errorUrl);
        }
    }

    public function disconnect(): JsonResponse
    {
        $connection = ZohoConnection::current();

        if ($connection) {
            $this->oauth->disconnect($connection);
        }

        return ApiResponse::success(['disconnected' => true]);
    }

    public function organizations(): JsonResponse
    {
        $orgs = ZohoOrganization::query()->orderBy('name')->get();

        return ApiResponse::success($orgs);
    }

    public function selectOrganization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zoho_org_id' => ['required', 'string'],
        ]);

        $connection = $this->connections->selectOrganization($data['zoho_org_id']);

        return ApiResponse::success([
            'organization_id' => $connection->organization_id,
            'organization_name' => $connection->organization_name,
        ]);
    }

    public function test(): JsonResponse
    {
        $result = $this->connections->testConnection();

        return ApiResponse::success($result);
    }

    public function sync(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, ['customers', 'invoices', 'full'], true)) {
            return ApiResponse::error('Invalid sync type.', [], 422);
        }

        $incremental = $request->boolean('incremental', true);
        $userId = Auth::id();
        $queuedIds = [];

        if ($type === 'full') {
            $parent = ZohoSyncJob::query()->create([
                'type' => ZohoSyncJob::TYPE_FULL,
                'status' => ZohoSyncJob::STATUS_PENDING,
                'triggered_by' => $userId,
            ]);
            $queuedIds[] = $parent->id;
        }

        if ($type === 'customers' || $type === 'full') {
            $customerJob = ZohoSyncJob::query()->create([
                'type' => ZohoSyncJob::TYPE_CUSTOMERS,
                'status' => ZohoSyncJob::STATUS_PENDING,
                'triggered_by' => $userId,
                'parent_job_id' => $parent->id ?? null,
            ]);
            SyncZohoCustomersJob::dispatch($customerJob->id, $incremental, $userId, $parent->id ?? null);
            $queuedIds[] = $customerJob->id;
        }

        if ($type === 'invoices' || $type === 'full') {
            $invoiceJob = ZohoSyncJob::query()->create([
                'type' => ZohoSyncJob::TYPE_INVOICES,
                'status' => ZohoSyncJob::STATUS_PENDING,
                'triggered_by' => $userId,
                'parent_job_id' => $parent->id ?? null,
            ]);
            SyncZohoInvoicesJob::dispatch($invoiceJob->id, $incremental, $userId, $parent->id ?? null);
            $queuedIds[] = $invoiceJob->id;
        }

        return ApiResponse::success([
            'queued' => true,
            'type' => $type,
            'sync_job_id' => $queuedIds[0] ?? null,
            'sync_job_ids' => $queuedIds,
        ], null, 202);
    }

    public function syncJobs(Request $request): JsonResponse
    {
        $jobs = ZohoSyncJob::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success($jobs->items(), [
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'per_page' => $jobs->perPage(),
            'total' => $jobs->total(),
        ]);
    }

    public function syncJob(int $id): JsonResponse
    {
        $job = ZohoSyncJob::query()->findOrFail($id);

        return ApiResponse::success($job);
    }

    public function retrySyncJob(int $id): JsonResponse
    {
        $job = ZohoSyncJob::query()->findOrFail($id);
        RetryFailedZohoSyncJob::dispatch($job->id);

        return ApiResponse::success(['queued' => true, 'sync_job_id' => $job->id], null, 202);
    }

    public function apiLogs(Request $request): JsonResponse
    {
        $logs = ZohoApiLog::query()
            ->when($request->filled('sync_job_id'), fn ($q) => $q->where('zoho_sync_job_id', $request->integer('sync_job_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success($logs->items(), [
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }

    public function branchMappings(): JsonResponse
    {
        $mappings = ZohoBranchMapping::query()->with('branch')->orderBy('id')->get();
        $mappings->each(function (ZohoBranchMapping $mapping) {
            $mapping->setAttribute('mapped_customer_count', Customer::withoutGlobalScopes()->where('branch_id', $mapping->branch_id)->count());
            $mapping->setAttribute('mapped_invoice_count', Invoice::withoutGlobalScopes()->where('branch_id', $mapping->branch_id)->count());
        });

        return ApiResponse::success($mappings);
    }

    public function previewAutoMatch(): JsonResponse
    {
        $locations = ZohoLocation::query()->get();
        $aliases = ZohoBranchAlias::query()->get()->groupBy('branch_id');
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/[^\pL\pN]+/u', '', $value) ?? '';

            return $value;
        };

        $matches = Branch::withoutGlobalScopes()->get()->map(function (Branch $branch) use ($locations, $aliases, $normalize) {
            $branchNames = collect([
                $branch->name_en,
                $branch->name_fa,
                $branch->code,
            ])->filter()->map(fn ($v) => $normalize((string) $v));

            foreach ($aliases->get($branch->id, collect()) as $alias) {
                $branchNames->push($normalize((string) ($alias->normalized_alias ?: $alias->alias)));
            }
            $branchNames = $branchNames->filter()->unique()->values();

            $hits = [];
            foreach ($locations as $location) {
                $locationKeys = collect([$location->name, $location->code])->filter()->map(fn ($v) => $normalize((string) $v));
                $exact = $branchNames->intersect($locationKeys)->isNotEmpty();
                if ($exact) {
                    $hits[] = ['location' => $location, 'class' => 'exact'];

                    continue;
                }

                foreach ($branchNames as $branchKey) {
                    foreach ($locationKeys as $locationKey) {
                        if ($branchKey !== '' && $locationKey !== '' && (
                            str_contains($locationKey, $branchKey) || str_contains($branchKey, $locationKey)
                        )) {
                            $hits[] = ['location' => $location, 'class' => 'probable'];
                            break 2;
                        }
                    }
                }
            }

            if ($hits === []) {
                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name_en,
                    'classification' => 'no_match',
                    'zoho_location_id' => null,
                    'location_name' => null,
                    'matched' => false,
                    'applicable' => false,
                ];
            }

            $uniqueLocationIds = collect($hits)->pluck('location.zoho_location_id')->unique();
            if ($uniqueLocationIds->count() > 1) {
                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name_en,
                    'classification' => 'ambiguous',
                    'zoho_location_id' => null,
                    'location_name' => null,
                    'candidates' => collect($hits)->map(fn ($hit) => [
                        'zoho_location_id' => $hit['location']->zoho_location_id,
                        'location_name' => $hit['location']->name,
                        'class' => $hit['class'],
                    ])->values(),
                    'matched' => false,
                    'applicable' => false,
                ];
            }

            $best = collect($hits)->firstWhere('class', 'exact') ?? $hits[0];
            $classification = $best['class'] === 'exact' ? 'exact' : 'probable';
            $alreadyLinked = $branch->zoho_location_id === $best['location']->zoho_location_id;

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name_en,
                'classification' => $alreadyLinked ? 'exact' : $classification,
                'zoho_location_id' => $best['location']->zoho_location_id,
                'location_name' => $best['location']->name,
                'matched' => true,
                'applicable' => in_array($classification, ['exact', 'probable'], true) && ! $alreadyLinked,
            ];
        });

        return ApiResponse::success($matches);
    }

    public function applyAutoMatch(Request $request): JsonResponse
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $matches = $this->previewAutoMatch()->getData(true)['data'] ?? [];
        $applied = 0;
        foreach ($matches as $match) {
            if (! ($match['applicable'] ?? false)) {
                continue;
            }
            if (($match['classification'] ?? '') === 'ambiguous') {
                continue;
            }
            $this->linkBranchToLocation((int) $match['branch_id'], (string) $match['zoho_location_id']);
            $applied++;
        }

        return ApiResponse::success(['applied' => $applied]);
    }

    public function linkLocation(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['zoho_location_id' => ['required', 'string', 'exists:zoho_locations,zoho_location_id']]);
        $branch = $this->linkBranchToLocation($id, $data['zoho_location_id']);

        return ApiResponse::success($branch);
    }

    protected function linkBranchToLocation(int $branchId, string $zohoLocationId): Branch
    {
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        $branch->update([
            'zoho_location_id' => $zohoLocationId,
            'mapping_type' => 'zoho_location',
            'zoho_sync_status' => 'linked',
            'last_structure_sync_at' => now(),
            'local_override' => false,
        ]);

        ZohoBranchMapping::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            [
                'mapping_method' => ZohoBranchMapping::METHOD_ZOHO_LOCATION,
                'zoho_value' => $zohoLocationId,
                'zoho_label' => ZohoLocation::query()->where('zoho_location_id', $zohoLocationId)->value('name'),
                'is_active' => true,
            ]
        );

        return $branch->fresh();
    }

    public function importLocation(string $zohoId): JsonResponse
    {
        $location = ZohoLocation::query()->where('zoho_location_id', $zohoId)->firstOrFail();
        $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $location->code ?: $location->name), 0, 12)) ?: 'ZOH';
        $code = $baseCode;
        $suffix = 1;
        while (Branch::withoutGlobalScopes()->where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix++;
        }
        $branch = Branch::withoutGlobalScopes()->create([
            'code' => $code, 'name_en' => $location->name, 'name_fa' => $location->name,
            'province_en' => 'Zoho', 'province_fa' => 'Zoho', 'receipt_prefix' => substr($code, 0, 8),
            'is_active' => $location->is_active, 'zoho_location_id' => $location->zoho_location_id,
            'mapping_type' => 'zoho_location', 'zoho_sync_status' => 'imported',
            'last_structure_sync_at' => now(), 'local_override' => false,
        ]);

        return ApiResponse::success($branch, null, 201);
    }

    public function resumeCircuit(string $type, ZohoCircuitBreaker $circuit): JsonResponse
    {
        return ApiResponse::success($circuit->resume($type));
    }

    public function cleanupFailedJobs(Request $request): JsonResponse
    {
        $validated = $request->validate(['apply' => ['sometimes', 'boolean'], 'only_timestamp' => ['sometimes', 'boolean']]);
        $arguments = ['--only-timestamp' => ($validated['only_timestamp'] ?? true) ? '1' : '0'];
        if ($validated['apply'] ?? false) {
            $arguments['--apply'] = true;
        }
        Artisan::call('zoho:failed-jobs-cleanup', $arguments);

        return ApiResponse::success(['output' => trim(Artisan::output()), 'applied' => (bool) ($validated['apply'] ?? false)]);
    }

    public function storeBranchMapping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'mapping_method' => ['required', 'string', Rule::in([
                ZohoBranchMapping::METHOD_ZOHO_BRANCH,
                ZohoBranchMapping::METHOD_ZOHO_LOCATION,
                ZohoBranchMapping::METHOD_REPORTING_TAG,
                ZohoBranchMapping::METHOD_CUSTOM_FIELD,
            ])],
            'zoho_value' => ['required', 'string', 'max:255'],
            'zoho_label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $mapping = ZohoBranchMapping::query()->updateOrCreate(
            ['branch_id' => $data['branch_id']],
            $data
        );

        return ApiResponse::success($mapping->load('branch'), null, 201);
    }

    public function updateBranchMapping(Request $request, int $id): JsonResponse
    {
        $mapping = ZohoBranchMapping::query()->findOrFail($id);

        $data = $request->validate([
            'mapping_method' => ['sometimes', 'string', Rule::in([
                ZohoBranchMapping::METHOD_ZOHO_BRANCH,
                ZohoBranchMapping::METHOD_ZOHO_LOCATION,
                ZohoBranchMapping::METHOD_REPORTING_TAG,
                ZohoBranchMapping::METHOD_CUSTOM_FIELD,
            ])],
            'zoho_value' => ['sometimes', 'string', 'max:255'],
            'zoho_label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $mapping->update($data);

        return ApiResponse::success($mapping->fresh()->load('branch'));
    }

    public function destroyBranchMapping(int $id): JsonResponse
    {
        ZohoBranchMapping::query()->findOrFail($id)->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function reportingTagMappings(): JsonResponse
    {
        return ApiResponse::success(
            ZohoReportingTagMapping::query()->with('branch')->orderBy('id')->get()
        );
    }

    public function upsertReportingTagMappings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mappings' => ['required', 'array'],
            'mappings.*.tag_id' => ['required', 'string'],
            'mappings.*.tag_option_id' => ['required', 'string'],
            'mappings.*.tag_name' => ['required', 'string'],
            'mappings.*.option_name' => ['required', 'string'],
            'mappings.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $saved = [];
        foreach ($data['mappings'] as $row) {
            $saved[] = ZohoReportingTagMapping::query()->updateOrCreate(
                [
                    'tag_id' => $row['tag_id'],
                    'tag_option_id' => $row['tag_option_id'],
                ],
                $row
            );
        }

        return ApiResponse::success($saved);
    }
}
