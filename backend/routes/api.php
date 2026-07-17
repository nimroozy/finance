<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CashboxController;
use App\Http\Controllers\Api\V1\CashboxTransferController;
use App\Http\Controllers\Api\V1\CashHandoverController;
use App\Http\Controllers\Api\V1\CashReconciliationController;
use App\Http\Controllers\Api\V1\CollectorController;
use App\Http\Controllers\Api\V1\CustodyReversalController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerPrefixMappingController;
use App\Http\Controllers\Api\V1\CustomerNoteController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DebtorController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentSettingController;
use App\Http\Controllers\Api\V1\PromiseController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RouteController as CollectionRouteController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VisitController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\Ownership\BranchPaymentMappingController;
use App\Http\Controllers\Api\V1\Ownership\BranchReceivablesController;
use App\Http\Controllers\Api\V1\Ownership\CollectorOwnershipController;
use App\Http\Controllers\Api\V1\Ownership\CustomerOwnershipController;
use App\Http\Controllers\Api\V1\Ownership\TemporaryAssignmentController;
use App\Http\Controllers\Api\V1\Zoho\FailedJobController;
use App\Http\Controllers\Api\V1\Zoho\ZohoController;
use App\Http\Controllers\Api\V1\Zoho\ZohoMappingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
        ->middleware('throttle:60,1')->name('whatsapp.webhook.verify');
    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('throttle:120,1')->name('whatsapp.webhook.receive');

    Route::get('/verify-receipt/{token}', [ReceiptController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('verify-receipt');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');
    });

    // OAuth callback is browser-redirected from Zoho (no Bearer token).
    Route::get('/zoho/oauth/callback', [ZohoController::class, 'oauthCallback'])
        ->name('zoho.oauth.callback');

    Route::middleware(['auth:sanctum', 'user.active', 'password.changed'])->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
        });

        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

        Route::middleware('permission:users.manage|users.view')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        });

        Route::middleware('permission:roles.view')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        });

        Route::middleware('permission:branches.view|branches.manage')->group(function () {
            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/{branch}', [BranchController::class, 'show'])
                ->whereNumber('branch')
                ->name('branches.show');
        });

        Route::middleware('permission:branches.manage')->group(function () {
            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])
                ->whereNumber('branch')
                ->name('branches.update');
        });

        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingController::class, 'show'])->name('settings.show');
            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        });

        // --- Zoho Books ---
        Route::middleware('permission:zoho.view')->group(function () {
            Route::get('/zoho/status', [ZohoController::class, 'status'])->name('zoho.status');
            Route::get('/zoho/health', [ZohoController::class, 'health'])->name('zoho.health');
            Route::get('/zoho/locations', [ZohoController::class, 'locations'])->name('zoho.locations');
            Route::get('/zoho/reporting-tags', [ZohoController::class, 'reportingTags'])->name('zoho.reporting-tags');
            Route::get('/zoho/payment-modes', [ZohoController::class, 'paymentModes'])->name('zoho.payment-modes');
            Route::get('/zoho/organizations', [ZohoController::class, 'organizations'])->name('zoho.organizations');
            Route::post('/zoho/test', [ZohoController::class, 'test'])->name('zoho.test');
            Route::get('/zoho/sync-jobs', [ZohoController::class, 'syncJobs'])->name('zoho.sync-jobs.index');
            Route::get('/zoho/sync-jobs/{id}', [ZohoController::class, 'syncJob'])
                ->whereNumber('id')
                ->name('zoho.sync-jobs.show');
            Route::get('/zoho/api-logs', [ZohoController::class, 'apiLogs'])->name('zoho.api-logs');
        });

        Route::middleware('permission:zoho.configure')->group(function () {
            Route::get('/zoho/location-mapping-review', [ZohoMappingController::class, 'review'])->name('zoho.location-mapping-review');
            Route::get('/zoho/mapping-conflicts', [ZohoMappingController::class, 'conflicts'])->name('zoho.mapping-conflicts');
            Route::post('/zoho/locations/{zohoId}/mapping-decision', [ZohoMappingController::class, 'decide'])->name('zoho.locations.mapping-decision');
            Route::post('/zoho/locations/{zohoId}/reprocess', [ZohoMappingController::class, 'reprocess'])->name('zoho.locations.reprocess');
            Route::get('/zoho/data-centers', [ZohoController::class, 'dataCenters'])->name('zoho.data-centers');
            Route::put('/zoho/settings', [ZohoController::class, 'updateSettings'])->name('zoho.settings');
            Route::get('/zoho/oauth/redirect', [ZohoController::class, 'oauthRedirect'])->name('zoho.oauth.redirect');
            Route::post('/zoho/disconnect', [ZohoController::class, 'disconnect'])->name('zoho.disconnect');
            Route::post('/zoho/organizations/select', [ZohoController::class, 'selectOrganization'])->name('zoho.organizations.select');
            Route::get('/zoho/branch-mappings', [ZohoController::class, 'branchMappings'])->name('zoho.branch-mappings.index');
            Route::post('/zoho/branch-mappings', [ZohoController::class, 'storeBranchMapping'])->name('zoho.branch-mappings.store');
            Route::put('/zoho/branch-mappings/{id}', [ZohoController::class, 'updateBranchMapping'])->whereNumber('id')->name('zoho.branch-mappings.update');
            Route::delete('/zoho/branch-mappings/{id}', [ZohoController::class, 'destroyBranchMapping'])->whereNumber('id')->name('zoho.branch-mappings.destroy');
            Route::get('/zoho/reporting-tag-mappings', [ZohoController::class, 'reportingTagMappings'])->name('zoho.reporting-tag-mappings.index');
            Route::put('/zoho/reporting-tag-mappings', [ZohoController::class, 'upsertReportingTagMappings'])->name('zoho.reporting-tag-mappings.upsert');
            Route::post('/zoho/branch-mappings/preview-auto-match', [ZohoController::class, 'previewAutoMatch'])->name('zoho.branch-mappings.preview-auto-match');
            Route::post('/zoho/branch-mappings/apply-auto-match', [ZohoController::class, 'applyAutoMatch'])->name('zoho.branch-mappings.apply-auto-match');
            Route::post('/zoho/branches/{id}/link-location', [ZohoController::class, 'linkLocation'])->whereNumber('id')->name('zoho.branches.link-location');
            Route::post('/zoho/locations/{zohoId}/import-as-branch', [ZohoController::class, 'importLocation'])->name('zoho.locations.import-as-branch');
        });

        Route::middleware('permission:zoho.sync')->group(function () {
            Route::get('/zoho/failed-jobs', [FailedJobController::class, 'index'])->name('zoho.failed-jobs.index');
            Route::post('/zoho/failed-jobs/archive', [FailedJobController::class, 'archive'])->name('zoho.failed-jobs.archive');
            Route::post('/zoho/sync/{type}', [ZohoController::class, 'sync'])
                ->where('type', 'customers|invoices|full')
                ->name('zoho.sync');
            Route::post('/zoho/sync-jobs/{id}/retry', [ZohoController::class, 'retrySyncJob'])
                ->whereNumber('id')
                ->name('zoho.sync-jobs.retry');
            Route::post('/zoho/structure/sync', [ZohoController::class, 'syncStructure'])->name('zoho.structure.sync');
            Route::post('/zoho/circuit-breakers/{type}/resume', [ZohoController::class, 'resumeCircuit'])->name('zoho.circuit-breakers.resume');
            Route::post('/zoho/failed-jobs/cleanup', [ZohoController::class, 'cleanupFailedJobs'])->name('zoho.failed-jobs.cleanup');
        });

        Route::middleware('permission:zoho.configure|customers.manage')->group(function () {
            Route::get('/customers/unmapped', [CustomerController::class, 'unmapped'])->name('customers.unmapped');
        });

        Route::middleware('permission:customers.manage')->group(function () {
            Route::post('/customers/{id}/map-branch', [CustomerController::class, 'mapBranch'])
                ->whereNumber('id')
                ->name('customers.map-branch');
        });

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{id}', [CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
        });

        Route::middleware('permission:invoices.view')->group(function () {
            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id')->name('invoices.show');
        });

        Route::middleware('permission:debtors.view')->group(function () {
            Route::get('/debtors', [DebtorController::class, 'index'])->name('debtors.index');
        });

        Route::middleware('permission:debtors.export')->group(function () {
            Route::get('/debtors/export', [DebtorController::class, 'export'])->name('debtors.export');
        });

        // --- Stage 3: Assignments ---
        Route::middleware('permission:assignments.view')->group(function () {
            Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('/assignments/unassigned-debtors', [AssignmentController::class, 'unassignedDebtors'])->name('assignments.unassigned-debtors');
            Route::get('/assignments/export', [AssignmentController::class, 'export'])->name('assignments.export');
            Route::get('/collectors/workload', [AssignmentController::class, 'collectorsWorkload'])->name('collectors.workload');
            Route::get('/assignments/{id}/history', [AssignmentController::class, 'history'])->whereNumber('id')->name('assignments.history');
            Route::get('/assignments/{id}', [AssignmentController::class, 'show'])->whereNumber('id')->name('assignments.show');
        });

        Route::middleware('permission:assignments.manage')->group(function () {
            Route::post('/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
            Route::post('/assignments/bulk', [AssignmentController::class, 'bulk'])->name('assignments.bulk');
            Route::post('/assignments/auto-preview', [AssignmentController::class, 'autoPreview'])->name('assignments.auto-preview');
            Route::post('/assignments/auto-confirm', [AssignmentController::class, 'autoConfirm'])->name('assignments.auto-confirm');
        });

        Route::middleware('permission:assignments.reassign')->group(function () {
            Route::post('/assignments/{id}/reassign', [AssignmentController::class, 'reassign'])->whereNumber('id')->name('assignments.reassign');
        });

        Route::middleware('permission:assignments.cancel')->group(function () {
            Route::post('/assignments/{id}/cancel', [AssignmentController::class, 'cancel'])->whereNumber('id')->name('assignments.cancel');
        });

        Route::post('/assignments/{id}/accept', [AssignmentController::class, 'accept'])->whereNumber('id')->name('assignments.accept');
        Route::post('/assignments/{id}/viewed', [AssignmentController::class, 'viewed'])->whereNumber('id')->name('assignments.viewed');

        // --- Stage 5.2: Permanent ownership / temporary assignments / branch payment mapping ---
        Route::middleware('permission:customer_ownership.view')->group(function () {
            Route::get('/customer-ownership', [CustomerOwnershipController::class, 'index']);
            Route::get('/customer-ownership/unassigned', [CustomerOwnershipController::class, 'unassigned']);
            Route::get('/customer-ownership/by-collector/{collectorId}', [CustomerOwnershipController::class, 'byCollector'])->whereNumber('collectorId');
            Route::get('/customer-ownership/history/{customerId}', [CustomerOwnershipController::class, 'history'])->whereNumber('customerId');
            Route::get('/customer-ownership/resolve/{customerId}', [CustomerOwnershipController::class, 'resolve'])->whereNumber('customerId');
            Route::get('/collector/permanent-customers', [CollectorOwnershipController::class, 'permanentCustomers']);
            Route::get('/collector/debtors', [CollectorOwnershipController::class, 'debtors']);
        });
        Route::middleware('permission:customer_ownership.create')->group(function () {
            Route::post('/customer-ownership', [CustomerOwnershipController::class, 'store']);
            Route::post('/customer-ownership/bulk', [CustomerOwnershipController::class, 'bulk']);
        });
        Route::middleware('permission:customer_ownership.transfer')->post('/customer-ownership/transfer', [CustomerOwnershipController::class, 'transfer']);
        Route::middleware('permission:customer_ownership.end')->post('/customer-ownership/{id}/end', [CustomerOwnershipController::class, 'end'])->whereNumber('id');
        Route::middleware('permission:ownership_conflicts.view')->get('/ownership-conflicts', [CustomerOwnershipController::class, 'conflicts']);
        Route::middleware('permission:ownership_conflicts.resolve')->post('/ownership-conflicts/{id}/resolve', [CustomerOwnershipController::class, 'resolveConflict'])->whereNumber('id');
        Route::middleware('permission:temporary_assignments.view')->get('/temporary-assignments', [TemporaryAssignmentController::class, 'index']);
        Route::middleware('permission:temporary_assignments.create')->post('/temporary-assignments', [TemporaryAssignmentController::class, 'store']);
        Route::middleware('permission:temporary_assignments.cancel')->post('/temporary-assignments/{id}/cancel', [TemporaryAssignmentController::class, 'cancel'])->whereNumber('id');
        Route::middleware('permission:branch_payment_mapping.view')->group(function () {
            Route::get('/branch-payment-mappings', [BranchPaymentMappingController::class, 'index']);
            Route::get('/branch-payment-mappings/accounts', [BranchPaymentMappingController::class, 'accounts']);
            Route::get('/branch-payment-mappings/payment-modes', [BranchPaymentMappingController::class, 'paymentModes']);
            Route::get('/branch-payment-mappings/{branchId}/readiness', [BranchPaymentMappingController::class, 'readiness'])->whereNumber('branchId');
        });
        Route::middleware('permission:branch_payment_mapping.manage')->post('/branch-payment-mappings', [BranchPaymentMappingController::class, 'upsert']);
        Route::middleware('permission:branch_payment_mapping.validate')->post('/branch-payment-mappings/{branchId}/validate', [BranchPaymentMappingController::class, 'validateMapping'])->whereNumber('branchId');

        Route::middleware('permission:customer_prefix_mapping.view')->group(function () {
            Route::get('/customer-prefix-mappings', [CustomerPrefixMappingController::class, 'index']);
            Route::post('/customer-prefix-mappings/test', [CustomerPrefixMappingController::class, 'testNumber']);
            Route::post('/customer-prefix-mappings/extract-preview', [CustomerPrefixMappingController::class, 'extractPreview']);
            Route::get('/customer-prefix-mappings/preview', [CustomerPrefixMappingController::class, 'preview']);
            Route::get('/customer-prefix-mappings/conflicts', [CustomerPrefixMappingController::class, 'conflicts']);
            Route::get('/customer-prefix-mappings/conflicts/{id}', [CustomerPrefixMappingController::class, 'conflictShow'])->whereNumber('id');
            Route::get('/customer-prefix-mappings/protected-history', [CustomerPrefixMappingController::class, 'protectedHistory']);
            Route::get('/customer-prefix-mappings/unmapped', [CustomerPrefixMappingController::class, 'unmappedIndex']);
            Route::get('/customer-prefix-mappings/history', [CustomerPrefixMappingController::class, 'history']);
            Route::get('/customer-prefix-mappings/report', [CustomerPrefixMappingController::class, 'reportSummary']);
            Route::get('/branches/{branchId}/prefix-metrics', [CustomerPrefixMappingController::class, 'branchMetrics'])->whereNumber('branchId');
        });
        Route::middleware('permission:customer_prefix_mapping.manage')->group(function () {
            Route::post('/customer-prefix-mappings', [CustomerPrefixMappingController::class, 'store']);
            Route::put('/customer-prefix-mappings/{id}', [CustomerPrefixMappingController::class, 'update'])->whereNumber('id');
            Route::post('/customer-prefix-mappings/{id}/disable', [CustomerPrefixMappingController::class, 'disable'])->whereNumber('id');
            Route::post('/customer-prefix-mappings/dry-run', [CustomerPrefixMappingController::class, 'dryRun']);
            Route::post('/customer-prefix-mappings/backfill-dry-run', [CustomerPrefixMappingController::class, 'backfillDryRun']);
            Route::post('/customer-prefix-mappings/conflicts/{id}/resolve', [CustomerPrefixMappingController::class, 'conflictResolve'])->whereNumber('id');
            Route::post('/customer-prefix-mappings/classify-unmapped', [CustomerPrefixMappingController::class, 'classifyUnmapped']);
            Route::post('/customer-prefix-mappings/customers/{id}/classify', [CustomerPrefixMappingController::class, 'classifyCustomer'])->whereNumber('id');
            Route::post('/branches/{branchId}/mark-headquarter', [CustomerPrefixMappingController::class, 'markBranchHeadquarter'])->whereNumber('branchId');
        });
        Route::middleware('permission:customer_prefix_mapping.apply')->group(function () {
            Route::post('/customer-prefix-mappings/apply', [CustomerPrefixMappingController::class, 'apply']);
            Route::post('/customer-prefix-mappings/backfill-apply', [CustomerPrefixMappingController::class, 'backfillApply']);
        });
        Route::middleware('permission:receivables_dashboard.view')->get('/reports/branch-receivables', [BranchReceivablesController::class, 'index']);

        // --- Visits ---
        Route::middleware('permission:visits.view')->group(function () {
            Route::get('/visits', [VisitController::class, 'index'])->name('visits.index');
            Route::get('/visits/outcomes', [VisitController::class, 'outcomes'])->name('visits.outcomes');
            Route::get('/visits/{id}', [VisitController::class, 'show'])->whereNumber('id')->name('visits.show');
        });

        Route::middleware('permission:visits.create|visits.manage')->group(function () {
            Route::post('/visits', [VisitController::class, 'store'])->name('visits.store');
        });

        Route::middleware('permission:visits.manage')->group(function () {
            Route::post('/visits/{id}/correction-note', [VisitController::class, 'correctionNote'])->whereNumber('id')->name('visits.correction-note');
        });

        // --- Routes ---
        Route::middleware('permission:routes.view')->group(function () {
            Route::get('/routes', [CollectionRouteController::class, 'index'])->name('routes.index');
            Route::get('/routes/{id}', [CollectionRouteController::class, 'show'])->whereNumber('id')->name('routes.show');
            Route::post('/routes/{id}/start', [CollectionRouteController::class, 'start'])->whereNumber('id')->name('routes.start');
            Route::post('/routes/{id}/complete', [CollectionRouteController::class, 'complete'])->whereNumber('id')->name('routes.complete');
        });

        Route::middleware('permission:routes.manage')->group(function () {
            Route::post('/routes', [CollectionRouteController::class, 'store'])->name('routes.store');
            Route::put('/routes/{id}', [CollectionRouteController::class, 'update'])->whereNumber('id')->name('routes.update');
            Route::delete('/routes/{id}', [CollectionRouteController::class, 'destroy'])->whereNumber('id')->name('routes.destroy');
            Route::post('/routes/{id}/publish', [CollectionRouteController::class, 'publish'])->whereNumber('id')->name('routes.publish');
            Route::post('/routes/{id}/cancel', [CollectionRouteController::class, 'cancel'])->whereNumber('id')->name('routes.cancel');
            Route::put('/routes/{id}/stops', [CollectionRouteController::class, 'reorderStops'])->whereNumber('id')->name('routes.reorder-stops');
        });

        // --- Promises ---
        Route::middleware('permission:promises.view')->group(function () {
            Route::get('/promises', [PromiseController::class, 'index'])->name('promises.index');
        });

        Route::middleware('permission:promises.create|promises.manage')->group(function () {
            Route::post('/promises', [PromiseController::class, 'store'])->name('promises.store');
            Route::post('/promises/{id}/cancel', [PromiseController::class, 'cancel'])->whereNumber('id')->name('promises.cancel');
        });

        Route::middleware('permission:promises.manage')->group(function () {
            Route::post('/promises/{id}/fulfill', [PromiseController::class, 'fulfill'])->whereNumber('id')->name('promises.fulfill');
            Route::post('/promises/{id}/supersede', [PromiseController::class, 'supersede'])->whereNumber('id')->name('promises.supersede');
        });

        // --- Notes ---
        Route::middleware('permission:notes.view')->group(function () {
            Route::get('/notes', [CustomerNoteController::class, 'index'])->name('notes.index');
        });

        Route::middleware('permission:notes.create|notes.manage')->group(function () {
            Route::post('/notes', [CustomerNoteController::class, 'store'])->name('notes.store');
            Route::patch('/notes/{id}', [CustomerNoteController::class, 'update'])->whereNumber('id')->name('notes.update');
        });

        // --- Evidence ---
        Route::middleware('permission:evidence.upload')->group(function () {
            Route::post('/visits/{id}/files', [EvidenceController::class, 'store'])->whereNumber('id')->name('visits.files.store');
        });

        Route::middleware('permission:evidence.view')->group(function () {
            Route::get('/files/{id}/download', [EvidenceController::class, 'download'])->whereNumber('id')->name('files.download');
        });

        // --- Notifications ---
        Route::middleware('permission:notifications.view')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->whereNumber('id')->name('notifications.read');
        });

        // --- Reports ---
        Route::middleware('permission:reports.assignments')->group(function () {
            Route::get('/reports/assignments-by-collector', [ReportController::class, 'assignmentsByCollector'])->name('reports.assignments-by-collector');
            Route::get('/reports/unassigned-debtors', [ReportController::class, 'unassignedDebtors'])->name('reports.unassigned-debtors');
        });

        Route::middleware('permission:reports.visits')->group(function () {
            Route::get('/reports/visits-by-outcome', [ReportController::class, 'visitsByOutcome'])->name('reports.visits-by-outcome');
            Route::get('/reports/gps-mismatch', [ReportController::class, 'gpsMismatch'])->name('reports.gps-mismatch');
        });

        Route::middleware('permission:reports.promises')->group(function () {
            Route::get('/reports/overdue-promises', [ReportController::class, 'overduePromises'])->name('reports.overdue-promises');
        });

        // --- Stage 4: Payments ---
        Route::middleware('permission:payments.view')->group(function () {
            Route::get('/payment-methods', [PaymentController::class, 'methods'])->name('payment-methods.index');
            Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('/payments/{uuid}', [PaymentController::class, 'show'])->name('payments.show');
            Route::get('/payments/{uuid}/sync-status', [PaymentController::class, 'syncStatus'])->name('payments.sync-status');
            Route::get('/reports/payments-summary', [ReportController::class, 'paymentsSummary'])->name('reports.payments-summary');
            Route::get('/reports/payments-sync-failures', [ReportController::class, 'paymentsSyncFailures'])->name('reports.payments-sync-failures');
        });

        Route::middleware('permission:payments.create|payments.manage')->group(function () {
            Route::post('/payments/preview', [PaymentController::class, 'preview'])->name('payments.preview');
            Route::post('/payments/draft', [PaymentController::class, 'draft'])->name('payments.draft');
        });

        Route::middleware('permission:payments.confirm|payments.create|payments.manage')->group(function () {
            Route::post('/payments/{uuid}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
        });

        Route::middleware('permission:payments.retry_sync')->group(function () {
            Route::post('/payments/{uuid}/retry-sync', [PaymentController::class, 'retrySync'])->name('payments.retry-sync');
        });

        Route::middleware('permission:reversals.request')->group(function () {
            Route::post('/payments/{uuid}/reversal-request', [PaymentController::class, 'requestReversal'])->name('payments.reversal-request');
        });

        Route::middleware('permission:reversals.approve')->group(function () {
            Route::post('/reversals/{id}/approve', [PaymentController::class, 'approveReversal'])->whereNumber('id')->name('reversals.approve');
            Route::post('/reversals/{id}/reject', [PaymentController::class, 'rejectReversal'])->whereNumber('id')->name('reversals.reject');
        });

        Route::middleware('permission:receipts.view')->group(function () {
            Route::get('/receipts/{uuid}', [ReceiptController::class, 'show'])->name('receipts.show');
            Route::get('/receipts/{uuid}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
        });

        Route::middleware('permission:receipts.print|receipts.manage')->group(function () {
            Route::post('/receipts/{uuid}/print-log', [ReceiptController::class, 'printLog'])->name('receipts.print-log');
        });

        Route::middleware('permission:wallets.view')->group(function () {
            Route::get('/collector/wallet', [WalletController::class, 'show'])->name('collector.wallet');
            Route::get('/collector/wallet/transactions', [WalletController::class, 'transactions'])->name('collector.wallet.transactions');
        });

        Route::middleware('permission:payment_settings.manage|payments.view')->group(function () {
            Route::get('/payment-settings', [PaymentSettingController::class, 'show'])->name('payment-settings.show');
        });

        Route::middleware('permission:payment_settings.manage')->group(function () {
            Route::put('/payment-settings', [PaymentSettingController::class, 'update'])->name('payment-settings.update');
        });

        // --- Stage 5: cash custody ---
        Route::middleware('permission:handovers.view')->group(function () {
            Route::get('/cash-handovers', [CashHandoverController::class, 'index'])->name('cash-handovers.index');
            Route::get('/cash-handovers/eligible', [CashHandoverController::class, 'eligible'])->name('cash-handovers.eligible');
            Route::get('/cash-handovers/{handover}', [CashHandoverController::class, 'show'])->name('cash-handovers.show');
        });
        Route::middleware('permission:handovers.create')->post('/cash-handovers/draft', [CashHandoverController::class, 'draft'])->name('cash-handovers.draft');
        Route::middleware('permission:handovers.submit')->post('/cash-handovers/{handover}/submit', [CashHandoverController::class, 'submit'])->name('cash-handovers.submit');
        Route::middleware('permission:handovers.review')->group(function () {
            Route::post('/cash-handovers/{handover}/approve', [CashHandoverController::class, 'approve'])->name('cash-handovers.approve');
            Route::post('/cash-handovers/{handover}/reject', [CashHandoverController::class, 'reject'])->name('cash-handovers.reject');
        });
        Route::middleware('permission:cashboxes.view')->group(function () {
            Route::get('/cashboxes', [CashboxController::class, 'index'])->name('cashboxes.index');
            Route::get('/cashboxes/{cashbox}', [CashboxController::class, 'show'])->name('cashboxes.show');
        });
        Route::middleware('permission:cashboxes.manage')->post('/cashboxes/ensure', [CashboxController::class, 'ensure'])->name('cashboxes.ensure');
        Route::middleware('permission:cashbox_transfers.view')->get('/cashbox-transfers', [CashboxTransferController::class, 'index'])->name('cashbox-transfers.index');
        Route::middleware('permission:cashbox_transfers.create')->post('/cashbox-transfers/draft', [CashboxTransferController::class, 'draft'])->name('cashbox-transfers.draft');
        Route::middleware('permission:cashbox_transfers.approve')->group(function () {
            Route::post('/cashbox-transfers/{transfer}/submit', [CashboxTransferController::class, 'submit'])->name('cashbox-transfers.submit');
            Route::post('/cashbox-transfers/{transfer}/approve', [CashboxTransferController::class, 'approve'])->name('cashbox-transfers.approve');
            Route::post('/cashbox-transfers/{transfer}/send', [CashboxTransferController::class, 'send'])->name('cashbox-transfers.send');
            Route::post('/cashbox-transfers/{transfer}/receive', [CashboxTransferController::class, 'receive'])->name('cashbox-transfers.receive');
            Route::post('/cashbox-transfers/{transfer}/reverse', [CashboxTransferController::class, 'reverse'])->name('cashbox-transfers.reverse');
        });
        Route::middleware('permission:cash_reconciliation.view')->get('/cash-reconciliations', [CashReconciliationController::class, 'index'])->name('cash-reconciliations.index');
        Route::middleware('permission:cash_reconciliation.run')->post('/cash-reconciliations/run', [CashReconciliationController::class, 'run'])->name('cash-reconciliations.run');

        Route::middleware('permission:custody_reversals.review|reversals.approve')->group(function () {
            Route::get('/custody-reversals', [CustodyReversalController::class, 'index'])->name('custody-reversals.index');
            Route::get('/custody-reversals/{id}', [CustodyReversalController::class, 'show'])->whereNumber('id')->name('custody-reversals.show');
            Route::post('/custody-reversals/{id}/approve', [CustodyReversalController::class, 'approve'])->whereNumber('id')->name('custody-reversals.approve');
            Route::post('/custody-reversals/{id}/reject', [CustodyReversalController::class, 'reject'])->whereNumber('id')->name('custody-reversals.reject');
            Route::post('/custody-reversals/{id}/retry-zoho', [CustodyReversalController::class, 'retryZoho'])->whereNumber('id')->name('custody-reversals.retry-zoho');
        });

        // --- Collectors ---
        Route::middleware('permission:collectors.view')->group(function () {
            Route::get('/collectors', [CollectorController::class, 'index'])->name('collectors.index');
        });

        Route::middleware('permission:collectors.manage')->group(function () {
            Route::post('/collectors', [CollectorController::class, 'store'])->name('collectors.store');
            Route::put('/collectors/{id}', [CollectorController::class, 'update'])->whereNumber('id')->name('collectors.update');
        });

        Route::get('/collector/dashboard', [CollectorController::class, 'dashboard'])->name('collector.dashboard');

        // --- Stage 6: WhatsApp Cloud API ---
        Route::middleware('permission:whatsapp.view')->group(function () {
            Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
            Route::get('/whatsapp/templates', [WhatsAppController::class, 'templates']);
            Route::get('/whatsapp/messages', [WhatsAppController::class, 'messages']);
            Route::get('/whatsapp/failures', [WhatsAppController::class, 'failures']);
            Route::get('/whatsapp/inbox', [WhatsAppController::class, 'inbox']);
            Route::get('/whatsapp/inbox/{conversation}', [WhatsAppController::class, 'showConversation'])->whereNumber('conversation');
            Route::get('/whatsapp/rules', [WhatsAppController::class, 'rules']);
        });
        Route::middleware('permission:whatsapp.manage')->group(function () {
            Route::post('/whatsapp/test-connection', [WhatsAppController::class, 'testConnection']);
            Route::post('/whatsapp/templates/sync', [WhatsAppController::class, 'syncTemplates']);
            Route::post('/whatsapp/pause', [WhatsAppController::class, 'pause']);
            Route::post('/whatsapp/resume', [WhatsAppController::class, 'resume']);
            Route::put('/whatsapp/rules', [WhatsAppController::class, 'saveRules']);
            Route::post('/whatsapp/inbox/{conversation}/resolve', [WhatsAppController::class, 'resolveConversation'])->whereNumber('conversation');
            Route::put('/whatsapp/preferences', [WhatsAppController::class, 'upsertPreferences']);
        });
        Route::middleware('permission:whatsapp.send_test')
            ->post('/whatsapp/send-test', [WhatsAppController::class, 'sendTest']);
    });
});
