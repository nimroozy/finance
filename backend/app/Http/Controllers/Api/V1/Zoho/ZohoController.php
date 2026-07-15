<?php

namespace App\Http\Controllers\Api\V1\Zoho;

use App\Http\Controllers\Controller;
use App\Jobs\RetryFailedZohoSyncJob;
use App\Jobs\SyncZohoCustomersJob;
use App\Jobs\SyncZohoInvoicesJob;
use App\Models\ZohoApiLog;
use App\Models\ZohoBranchMapping;
use App\Models\ZohoConnection;
use App\Models\ZohoOrganization;
use App\Models\ZohoReportingTagMapping;
use App\Models\ZohoSyncJob;
use App\Services\Zoho\ZohoConfig;
use App\Services\Zoho\ZohoConnectionService;
use App\Services\Zoho\ZohoOAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return ApiResponse::success(
            ZohoBranchMapping::query()->with('branch')->orderBy('id')->get()
        );
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
