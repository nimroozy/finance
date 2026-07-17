<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserUiPreferenceController;
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

    Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\Api\V1\AttachmentController::class, 'download'])
        ->name('attachments.download');

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

        Route::prefix('me')->name('me.')->group(function () {
            Route::get('/ui-preferences', [UserUiPreferenceController::class, 'show'])->name('ui-preferences.show');
            Route::put('/ui-preferences', [UserUiPreferenceController::class, 'update'])->name('ui-preferences.update');
            Route::put('/ui-preferences/favorites/reorder', [UserUiPreferenceController::class, 'reorderFavorites'])->name('ui-preferences.favorites.reorder');
            Route::post('/ui-preferences/favorites/{appId}', [UserUiPreferenceController::class, 'addFavorite'])->name('ui-preferences.favorites.add');
            Route::delete('/ui-preferences/favorites/{appId}', [UserUiPreferenceController::class, 'removeFavorite'])->name('ui-preferences.favorites.remove');
            Route::post('/ui-preferences/recent/{appId}', [UserUiPreferenceController::class, 'recordRecent'])->name('ui-preferences.recent');
        });

        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

        Route::get('/system/version', \App\Http\Controllers\Api\V1\SystemVersionController::class)
            ->name('system.version');

        Route::prefix('pickers')->name('pickers.')->group(function () {
            Route::get('/customers', [\App\Http\Controllers\Api\V1\PickerController::class, 'customers'])->name('customers');
            Route::get('/users', [\App\Http\Controllers\Api\V1\PickerController::class, 'users'])->name('users');
            Route::get('/departments', [\App\Http\Controllers\Api\V1\PickerController::class, 'departments'])->name('departments');
            Route::get('/teams', [\App\Http\Controllers\Api\V1\PickerController::class, 'teams'])->name('teams');
            Route::get('/branches', [\App\Http\Controllers\Api\V1\PickerController::class, 'branches'])->name('branches');
        });

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

        // --- Stage 7: Ticketing / Tasks / Installations / SLA ---
        Route::middleware('permission:tickets.view|tickets.view_all_branch|tickets.view_all')->group(function () {
            Route::get('/tickets', [\App\Http\Controllers\Api\V1\TicketController::class, 'index'])->name('tickets.index');
            Route::get('/tickets/{id}', [\App\Http\Controllers\Api\V1\TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
            Route::get('/tickets/{id}/attachments', [\App\Http\Controllers\Api\V1\AttachmentController::class, 'forTicket'])->whereNumber('id')->name('tickets.attachments');
        });
        Route::middleware('permission:tickets.create')->post('/tickets', [\App\Http\Controllers\Api\V1\TicketController::class, 'store'])->name('tickets.store');
        Route::middleware('permission:tickets.update')->group(function () {
            Route::put('/tickets/{id}', [\App\Http\Controllers\Api\V1\TicketController::class, 'update'])->whereNumber('id')->name('tickets.update');
            Route::post('/tickets/{id}/transition', [\App\Http\Controllers\Api\V1\TicketController::class, 'transition'])->whereNumber('id')->name('tickets.transition');
            Route::post('/tickets/{id}/watchers', [\App\Http\Controllers\Api\V1\TicketController::class, 'watchers'])->whereNumber('id')->name('tickets.watchers');
        });
        Route::middleware('permission:tickets.assign')->post('/tickets/{id}/assign', [\App\Http\Controllers\Api\V1\TicketController::class, 'assign'])->whereNumber('id')->name('tickets.assign');
        Route::middleware('permission:tickets.resolve')->post('/tickets/{id}/resolve', [\App\Http\Controllers\Api\V1\TicketController::class, 'resolve'])->whereNumber('id')->name('tickets.resolve');
        Route::middleware('permission:tickets.close')->post('/tickets/{id}/close', [\App\Http\Controllers\Api\V1\TicketController::class, 'close'])->whereNumber('id')->name('tickets.close');
        Route::middleware('permission:tickets.reopen')->post('/tickets/{id}/reopen', [\App\Http\Controllers\Api\V1\TicketController::class, 'reopen'])->whereNumber('id')->name('tickets.reopen');

        Route::middleware('permission:tickets.view|ticket_types.manage')->get('/ticket-types', [\App\Http\Controllers\Api\V1\TicketTypeController::class, 'index'])->name('ticket-types.index');
        Route::middleware('permission:ticket_types.manage')->group(function () {
            Route::post('/ticket-types', [\App\Http\Controllers\Api\V1\TicketTypeController::class, 'store'])->name('ticket-types.store');
            Route::put('/ticket-types/{id}', [\App\Http\Controllers\Api\V1\TicketTypeController::class, 'update'])->whereNumber('id')->name('ticket-types.update');
        });

        Route::middleware('permission:tasks.view')->group(function () {
            Route::get('/tasks', [\App\Http\Controllers\Api\V1\TaskController::class, 'index'])->name('tasks.index');
            Route::get('/tasks/my', [\App\Http\Controllers\Api\V1\TaskController::class, 'my'])->name('tasks.my');
            Route::get('/tasks/{id}', [\App\Http\Controllers\Api\V1\TaskController::class, 'show'])->whereNumber('id')->name('tasks.show');
            Route::get('/tasks/{id}/attachments', [\App\Http\Controllers\Api\V1\AttachmentController::class, 'forTask'])->whereNumber('id')->name('tasks.attachments');
        });
        Route::middleware('permission:tasks.create')->post('/tasks', [\App\Http\Controllers\Api\V1\TaskController::class, 'store'])->name('tasks.store');
        Route::middleware('permission:tasks.assign')->post('/tasks/{id}/offer', [\App\Http\Controllers\Api\V1\TaskController::class, 'offer'])->whereNumber('id')->name('tasks.offer');
        Route::middleware('permission:tasks.accept')->group(function () {
            Route::post('/tasks/{id}/accept', [\App\Http\Controllers\Api\V1\TaskController::class, 'accept'])->whereNumber('id')->name('tasks.accept');
            Route::post('/tasks/{id}/reject', [\App\Http\Controllers\Api\V1\TaskController::class, 'reject'])->whereNumber('id')->name('tasks.reject');
        });
        Route::middleware('permission:tasks.complete')->group(function () {
            Route::post('/tasks/{id}/start-travel', [\App\Http\Controllers\Api\V1\TaskController::class, 'startTravel'])->whereNumber('id')->name('tasks.start-travel');
            Route::post('/tasks/{id}/arrive', [\App\Http\Controllers\Api\V1\TaskController::class, 'arrive'])->whereNumber('id')->name('tasks.arrive');
            Route::post('/tasks/{id}/start', [\App\Http\Controllers\Api\V1\TaskController::class, 'start'])->whereNumber('id')->name('tasks.start');
            Route::post('/tasks/{id}/complete', [\App\Http\Controllers\Api\V1\TaskController::class, 'complete'])->whereNumber('id')->name('tasks.complete');
            Route::post('/tasks/{id}/block', [\App\Http\Controllers\Api\V1\TaskController::class, 'block'])->whereNumber('id')->name('tasks.block');
        });
        Route::middleware('permission:tasks.verify')->post('/tasks/{id}/verify', [\App\Http\Controllers\Api\V1\TaskController::class, 'verify'])->whereNumber('id')->name('tasks.verify');
        Route::middleware('permission:tasks.reassign')->post('/tasks/{id}/reassign', [\App\Http\Controllers\Api\V1\TaskController::class, 'reassign'])->whereNumber('id')->name('tasks.reassign');
        Route::middleware('permission:tasks.cancel')->post('/tasks/{id}/cancel', [\App\Http\Controllers\Api\V1\TaskController::class, 'cancel'])->whereNumber('id')->name('tasks.cancel');

        Route::middleware('permission:tickets.view|tasks.view')->group(function () {
            Route::get('/work-logs', [\App\Http\Controllers\Api\V1\WorkLogController::class, 'index'])->name('work-logs.index');
            Route::post('/work-logs', [\App\Http\Controllers\Api\V1\WorkLogController::class, 'store'])->name('work-logs.store');
            Route::get('/departments', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'index'])->name('departments.index');
        });

        Route::middleware('permission:attachments.upload')->post('/attachments', [\App\Http\Controllers\Api\V1\AttachmentController::class, 'upload'])->name('attachments.upload');

        Route::middleware('permission:sla.manage|tickets.view')->get('/sla-policies', [\App\Http\Controllers\Api\V1\SlaPolicyController::class, 'index'])->name('sla-policies.index');
        Route::middleware('permission:sla.manage')->group(function () {
            Route::post('/sla-policies', [\App\Http\Controllers\Api\V1\SlaPolicyController::class, 'store'])->name('sla-policies.store');
            Route::put('/sla-policies/{id}', [\App\Http\Controllers\Api\V1\SlaPolicyController::class, 'update'])->whereNumber('id')->name('sla-policies.update');
        });

        Route::middleware('permission:escalations.view')->get('/escalations', [\App\Http\Controllers\Api\V1\EscalationController::class, 'index'])->name('escalations.index');
        Route::middleware('permission:escalations.manage')->group(function () {
            Route::post('/escalations', [\App\Http\Controllers\Api\V1\EscalationController::class, 'store'])->name('escalations.store');
            Route::post('/escalations/{id}/acknowledge', [\App\Http\Controllers\Api\V1\EscalationController::class, 'acknowledge'])->whereNumber('id')->name('escalations.acknowledge');
        });

        Route::middleware('permission:installations.view')->group(function () {
            Route::get('/installations', [\App\Http\Controllers\Api\V1\InstallationController::class, 'index'])->name('installations.index');
            Route::get('/installations/{id}', [\App\Http\Controllers\Api\V1\InstallationController::class, 'show'])->whereNumber('id')->name('installations.show');
        });
        Route::middleware('permission:installations.create')->post('/installations', [\App\Http\Controllers\Api\V1\InstallationController::class, 'store'])->name('installations.store');
        Route::middleware('permission:installations.update')->post('/installations/{id}/transition', [\App\Http\Controllers\Api\V1\InstallationController::class, 'transition'])->whereNumber('id')->name('installations.transition');

        Route::middleware('permission:whatsapp.ticket_intake|tickets.view')->get('/ticket-intake', [\App\Http\Controllers\Api\V1\TicketIntakeController::class, 'index'])->name('ticket-intake.index');
        Route::middleware('permission:whatsapp.ticket_intake|tickets.create')->post('/ticket-intake/{id}/create-ticket', [\App\Http\Controllers\Api\V1\TicketIntakeController::class, 'createTicket'])->whereNumber('id')->name('ticket-intake.create-ticket');
        Route::middleware('permission:whatsapp.ticket_intake')->post('/ticket-intake/{id}/dismiss', [\App\Http\Controllers\Api\V1\TicketIntakeController::class, 'dismiss'])->whereNumber('id')->name('ticket-intake.dismiss');

        Route::middleware('permission:tickets.view|dashboard.view')->get('/operations/dashboard/support', [\App\Http\Controllers\Api\V1\OperationsDashboardController::class, 'support'])->name('operations.dashboard.support');
        Route::middleware('permission:tasks.view|dashboard.view')->get('/operations/dashboard/technical', [\App\Http\Controllers\Api\V1\OperationsDashboardController::class, 'technical'])->name('operations.dashboard.technical');
        Route::middleware('permission:escalations.view|dashboard.view')->get('/operations/dashboard/noc', [\App\Http\Controllers\Api\V1\OperationsDashboardController::class, 'noc'])->name('operations.dashboard.noc');
        Route::middleware('permission:installations.view|dashboard.view')->get('/operations/dashboard/finance', [\App\Http\Controllers\Api\V1\OperationsDashboardController::class, 'finance'])->name('operations.dashboard.finance');
        Route::middleware('permission:dashboard.view')->get('/operations/dashboard/manager', [\App\Http\Controllers\Api\V1\OperationsDashboardController::class, 'manager'])->name('operations.dashboard.manager');

        Route::middleware('permission:tickets.view|tasks.view|installations.view')->get('/operations/search', \App\Http\Controllers\Api\V1\OperationsSearchController::class)->name('operations.search');
        Route::middleware('permission:tickets.view')->get('/operations/reports/tickets', [\App\Http\Controllers\Api\V1\OperationsReportController::class, 'tickets'])->name('operations.reports.tickets');
        Route::middleware('permission:tasks.view')->get('/operations/reports/tasks', [\App\Http\Controllers\Api\V1\OperationsReportController::class, 'tasks'])->name('operations.reports.tasks');
        Route::middleware('permission:tickets.view|customers.view')->get('/customers/{customer}/timeline', [\App\Http\Controllers\Api\V1\CustomerTimelineController::class, 'show'])->whereNumber('customer')->name('customers.timeline');

        Route::middleware('permission:task_templates.manage|tasks.view')->get('/task-templates', [\App\Http\Controllers\Api\V1\TaskTemplateController::class, 'index'])->name('task-templates.index');
        Route::middleware('permission:task_templates.manage')->group(function () {
            Route::post('/task-templates', [\App\Http\Controllers\Api\V1\TaskTemplateController::class, 'store'])->name('task-templates.store');
            Route::put('/task-templates/{id}', [\App\Http\Controllers\Api\V1\TaskTemplateController::class, 'update'])->whereNumber('id')->name('task-templates.update');
        });

        Route::middleware('permission:escalations.manage|sla.manage')->group(function () {
            Route::get('/escalation-rules', [\App\Http\Controllers\Api\V1\EscalationRuleController::class, 'index'])->name('escalation-rules.index');
            Route::post('/escalation-rules', [\App\Http\Controllers\Api\V1\EscalationRuleController::class, 'store'])->name('escalation-rules.store');
            Route::put('/escalation-rules/{id}', [\App\Http\Controllers\Api\V1\EscalationRuleController::class, 'update'])->whereNumber('id')->name('escalation-rules.update');
        });

        // Stage 8 — CRM / sales pipeline
        Route::middleware('permission:crm.leads.view|crm.leads.create')->get('/crm/lead-sources', [\App\Http\Controllers\Api\V1\Crm\LeadSourceController::class, 'index'])->name('crm.lead-sources.index');

        Route::middleware('permission:crm.leads.view')->group(function () {
            Route::get('/crm/leads', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'index'])->name('crm.leads.index');
            Route::get('/crm/leads/{id}', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'show'])->whereNumber('id')->name('crm.leads.show');
            Route::get('/crm/leads/{id}/timeline', [\App\Http\Controllers\Api\V1\Crm\CrmTimelineController::class, 'show'])->whereNumber('id')->name('crm.leads.timeline');
        });
        Route::middleware('permission:crm.leads.create')->post('/crm/leads', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'store'])->name('crm.leads.store');
        Route::middleware('permission:crm.leads.update')->group(function () {
            Route::put('/crm/leads/{id}', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'update'])->whereNumber('id')->name('crm.leads.update');
            Route::post('/crm/leads/{id}/transition', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'transition'])->whereNumber('id')->name('crm.leads.transition');
        });
        Route::middleware('permission:crm.leads.assign')->post('/crm/leads/{id}/assign', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'assign'])->whereNumber('id')->name('crm.leads.assign');
        Route::middleware('permission:crm.leads.convert')->post('/crm/leads/{id}/convert', [\App\Http\Controllers\Api\V1\Crm\LeadController::class, 'convert'])->whereNumber('id')->name('crm.leads.convert');
        Route::middleware('permission:crm.leads.update')->post('/crm/leads/{id}/link-zoho-customer', [\App\Http\Controllers\Api\V1\Crm\ZohoCustomerLinkController::class, 'link'])->whereNumber('id')->name('crm.leads.link-zoho-customer');
        Route::middleware('permission:crm.leads.view|customers.view')->get('/crm/customers/search-zoho-mirror', [\App\Http\Controllers\Api\V1\Crm\ZohoCustomerLinkController::class, 'search'])->name('crm.customers.search-zoho-mirror');

        Route::middleware('permission:crm.activities.manage|crm.leads.view')->get('/crm/activities', [\App\Http\Controllers\Api\V1\Crm\ActivityController::class, 'index'])->name('crm.activities.index');
        Route::middleware('permission:crm.activities.manage')->post('/crm/activities', [\App\Http\Controllers\Api\V1\Crm\ActivityController::class, 'store'])->name('crm.activities.store');

        Route::middleware('permission:crm.follow_ups.manage|crm.leads.view')->get('/crm/follow-ups', [\App\Http\Controllers\Api\V1\Crm\FollowUpController::class, 'index'])->name('crm.follow-ups.index');
        Route::middleware('permission:crm.follow_ups.manage')->group(function () {
            Route::post('/crm/follow-ups', [\App\Http\Controllers\Api\V1\Crm\FollowUpController::class, 'store'])->name('crm.follow-ups.store');
            Route::post('/crm/follow-ups/{id}/complete', [\App\Http\Controllers\Api\V1\Crm\FollowUpController::class, 'complete'])->whereNumber('id')->name('crm.follow-ups.complete');
            Route::post('/crm/follow-ups/{id}/cancel', [\App\Http\Controllers\Api\V1\Crm\FollowUpController::class, 'cancel'])->whereNumber('id')->name('crm.follow-ups.cancel');
        });

        Route::middleware('permission:crm.coverage.manage|crm.leads.view')->get('/crm/coverage-checks', [\App\Http\Controllers\Api\V1\Crm\CoverageCheckController::class, 'index'])->name('crm.coverage-checks.index');
        Route::middleware('permission:crm.coverage.manage')->post('/crm/coverage-checks', [\App\Http\Controllers\Api\V1\Crm\CoverageCheckController::class, 'store'])->name('crm.coverage-checks.store');

        Route::middleware('permission:crm.surveys.view|crm.surveys.manage')->get('/crm/site-surveys', [\App\Http\Controllers\Api\V1\Crm\SiteSurveyController::class, 'index'])->name('crm.site-surveys.index');
        Route::middleware('permission:crm.surveys.manage')->group(function () {
            Route::post('/crm/site-surveys', [\App\Http\Controllers\Api\V1\Crm\SiteSurveyController::class, 'store'])->name('crm.site-surveys.store');
            Route::post('/crm/site-surveys/{id}/complete', [\App\Http\Controllers\Api\V1\Crm\SiteSurveyController::class, 'complete'])->whereNumber('id')->name('crm.site-surveys.complete');
        });

        Route::middleware('permission:crm.quotations.view')->get('/crm/quotations', [\App\Http\Controllers\Api\V1\Crm\QuotationController::class, 'index'])->name('crm.quotations.index');
        Route::middleware('permission:crm.quotations.create')->group(function () {
            Route::post('/crm/quotations', [\App\Http\Controllers\Api\V1\Crm\QuotationController::class, 'store'])->name('crm.quotations.store');
            Route::post('/crm/quotations/{id}/send', [\App\Http\Controllers\Api\V1\Crm\QuotationController::class, 'send'])->whereNumber('id')->name('crm.quotations.send');
        });
        Route::middleware('permission:crm.quotations.approve')->post('/crm/quotations/{id}/accept', [\App\Http\Controllers\Api\V1\Crm\QuotationController::class, 'accept'])->whereNumber('id')->name('crm.quotations.accept');
        Route::middleware('permission:crm.quotations.approve|crm.quotations.create')->post('/crm/quotations/{id}/reject', [\App\Http\Controllers\Api\V1\Crm\QuotationController::class, 'reject'])->whereNumber('id')->name('crm.quotations.reject');

        Route::middleware('permission:crm.pipeline.manage|crm.leads.view')->get('/crm/opportunities', [\App\Http\Controllers\Api\V1\Crm\OpportunityController::class, 'index'])->name('crm.opportunities.index');
        Route::middleware('permission:crm.pipeline.manage')->group(function () {
            Route::post('/crm/opportunities', [\App\Http\Controllers\Api\V1\Crm\OpportunityController::class, 'store'])->name('crm.opportunities.store');
            Route::put('/crm/opportunities/{id}', [\App\Http\Controllers\Api\V1\Crm\OpportunityController::class, 'update'])->whereNumber('id')->name('crm.opportunities.update');
        });

        Route::middleware('permission:crm.targets.view|crm.targets.manage')->get('/crm/sales-targets', [\App\Http\Controllers\Api\V1\Crm\SalesTargetController::class, 'index'])->name('crm.sales-targets.index');
        Route::middleware('permission:crm.targets.manage')->post('/crm/sales-targets', [\App\Http\Controllers\Api\V1\Crm\SalesTargetController::class, 'store'])->name('crm.sales-targets.store');

        Route::middleware('permission:crm.reports.view|crm.leads.view|dashboard.view')->get('/crm/dashboard', \App\Http\Controllers\Api\V1\Crm\CrmDashboardController::class)->name('crm.dashboard');
        Route::middleware('permission:crm.reports.view')->get('/crm/reports/leads', [\App\Http\Controllers\Api\V1\Crm\CrmReportController::class, 'leads'])->name('crm.reports.leads');

        // Stage 9 — Inventory
        Route::middleware('permission:inventory.products.view|inventory.products.manage')->group(function () {
            Route::get('/inventory/products', [\App\Http\Controllers\Api\V1\Inventory\ProductController::class, 'index'])->name('inventory.products.index');
            Route::get('/inventory/products/{id}', [\App\Http\Controllers\Api\V1\Inventory\ProductController::class, 'show'])->whereNumber('id')->name('inventory.products.show');
        });
        Route::middleware('permission:inventory.products.manage')->group(function () {
            Route::post('/inventory/products', [\App\Http\Controllers\Api\V1\Inventory\ProductController::class, 'store'])->name('inventory.products.store');
            Route::put('/inventory/products/{id}', [\App\Http\Controllers\Api\V1\Inventory\ProductController::class, 'update'])->whereNumber('id')->name('inventory.products.update');
        });

        Route::middleware('permission:inventory.products.view|inventory.categories.manage')->get('/inventory/categories', [\App\Http\Controllers\Api\V1\Inventory\CategoryController::class, 'index'])->name('inventory.categories.index');
        Route::middleware('permission:inventory.categories.manage')->group(function () {
            Route::post('/inventory/categories', [\App\Http\Controllers\Api\V1\Inventory\CategoryController::class, 'store'])->name('inventory.categories.store');
            Route::put('/inventory/categories/{id}', [\App\Http\Controllers\Api\V1\Inventory\CategoryController::class, 'update'])->whereNumber('id')->name('inventory.categories.update');
        });

        Route::middleware('permission:inventory.locations.view|inventory.locations.manage')->get('/inventory/locations', [\App\Http\Controllers\Api\V1\Inventory\LocationController::class, 'index'])->name('inventory.locations.index');
        Route::middleware('permission:inventory.locations.manage')->group(function () {
            Route::post('/inventory/locations', [\App\Http\Controllers\Api\V1\Inventory\LocationController::class, 'store'])->name('inventory.locations.store');
            Route::put('/inventory/locations/{id}', [\App\Http\Controllers\Api\V1\Inventory\LocationController::class, 'update'])->whereNumber('id')->name('inventory.locations.update');
        });

        Route::middleware('permission:inventory.sites.view|inventory.sites.manage')->group(function () {
            Route::get('/inventory/sites', [\App\Http\Controllers\Api\V1\Inventory\SiteController::class, 'index'])->name('inventory.sites.index');
            Route::get('/inventory/sites/{id}', [\App\Http\Controllers\Api\V1\Inventory\SiteController::class, 'show'])->whereNumber('id')->name('inventory.sites.show');
        });
        Route::middleware('permission:inventory.sites.manage')->group(function () {
            Route::post('/inventory/sites', [\App\Http\Controllers\Api\V1\Inventory\SiteController::class, 'store'])->name('inventory.sites.store');
            Route::put('/inventory/sites/{id}', [\App\Http\Controllers\Api\V1\Inventory\SiteController::class, 'update'])->whereNumber('id')->name('inventory.sites.update');
        });

        Route::middleware('permission:inventory.towers.view|inventory.towers.manage')->get('/inventory/towers', [\App\Http\Controllers\Api\V1\Inventory\TowerController::class, 'index'])->name('inventory.towers.index');
        Route::middleware('permission:inventory.towers.manage')->group(function () {
            Route::post('/inventory/towers', [\App\Http\Controllers\Api\V1\Inventory\TowerController::class, 'store'])->name('inventory.towers.store');
            Route::put('/inventory/towers/{id}', [\App\Http\Controllers\Api\V1\Inventory\TowerController::class, 'update'])->whereNumber('id')->name('inventory.towers.update');
        });

        Route::middleware('permission:inventory.suppliers.view|inventory.suppliers.manage')->get('/inventory/suppliers', [\App\Http\Controllers\Api\V1\Inventory\SupplierController::class, 'index'])->name('inventory.suppliers.index');
        Route::middleware('permission:inventory.suppliers.manage')->group(function () {
            Route::post('/inventory/suppliers', [\App\Http\Controllers\Api\V1\Inventory\SupplierController::class, 'store'])->name('inventory.suppliers.store');
            Route::put('/inventory/suppliers/{id}', [\App\Http\Controllers\Api\V1\Inventory\SupplierController::class, 'update'])->whereNumber('id')->name('inventory.suppliers.update');
        });

        Route::middleware('permission:inventory.stock.view')->group(function () {
            Route::get('/inventory/stock/balances', [\App\Http\Controllers\Api\V1\Inventory\StockController::class, 'balances'])->name('inventory.stock.balances');
            Route::get('/inventory/stock/transactions', [\App\Http\Controllers\Api\V1\Inventory\StockController::class, 'transactions'])->name('inventory.stock.transactions');
        });
        Route::middleware('permission:inventory.stock.adjust')->post('/inventory/stock/adjust', [\App\Http\Controllers\Api\V1\Inventory\StockController::class, 'adjust'])->name('inventory.stock.adjust');

        Route::middleware('permission:inventory.reservations.view|inventory.reservations.manage')->group(function () {
            Route::get('/inventory/reservations', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'index'])->name('inventory.reservations.index');
            Route::get('/inventory/reservations/{id}', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'show'])->whereNumber('id')->name('inventory.reservations.show');
        });
        Route::middleware('permission:inventory.reservations.manage')->group(function () {
            Route::post('/inventory/reservations', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'store'])->name('inventory.reservations.store');
            Route::post('/inventory/reservations/{id}/reserve', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'reserve'])->whereNumber('id')->name('inventory.reservations.reserve');
            Route::post('/inventory/reservations/{id}/fulfill', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'fulfill'])->whereNumber('id')->name('inventory.reservations.fulfill');
            Route::post('/inventory/reservations/{id}/release', [\App\Http\Controllers\Api\V1\Inventory\ReservationController::class, 'release'])->whereNumber('id')->name('inventory.reservations.release');
        });

        Route::middleware('permission:inventory.transfers.view|inventory.transfers.manage')->group(function () {
            Route::get('/inventory/transfers', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'index'])->name('inventory.transfers.index');
            Route::get('/inventory/transfers/{id}', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'show'])->whereNumber('id')->name('inventory.transfers.show');
        });
        Route::middleware('permission:inventory.transfers.manage|inventory.stock.transfer')->group(function () {
            Route::post('/inventory/transfers', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'store'])->name('inventory.transfers.store');
            Route::post('/inventory/transfers/{id}/submit', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'submit'])->whereNumber('id')->name('inventory.transfers.submit');
            Route::post('/inventory/transfers/{id}/dispatch', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'dispatchTransfer'])->whereNumber('id')->name('inventory.transfers.dispatch');
            Route::post('/inventory/transfers/{id}/receive', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'receive'])->whereNumber('id')->name('inventory.transfers.receive');
            Route::post('/inventory/transfers/{id}/close', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'close'])->whereNumber('id')->name('inventory.transfers.close');
        });
        Route::middleware('permission:inventory.transfers.approve')->post('/inventory/transfers/{id}/approve', [\App\Http\Controllers\Api\V1\Inventory\TransferController::class, 'approve'])->whereNumber('id')->name('inventory.transfers.approve');

        Route::middleware('permission:inventory.equipment.view|inventory.equipment.manage')->group(function () {
            Route::get('/inventory/equipment', [\App\Http\Controllers\Api\V1\Inventory\EquipmentController::class, 'index'])->name('inventory.equipment.index');
            Route::get('/inventory/equipment/{id}', [\App\Http\Controllers\Api\V1\Inventory\EquipmentController::class, 'show'])->whereNumber('id')->name('inventory.equipment.show');
        });
        Route::middleware('permission:inventory.equipment.manage')->group(function () {
            Route::post('/inventory/equipment/receive', [\App\Http\Controllers\Api\V1\Inventory\EquipmentController::class, 'receive'])->name('inventory.equipment.receive');
            Route::post('/inventory/equipment/{id}/move', [\App\Http\Controllers\Api\V1\Inventory\EquipmentController::class, 'move'])->whereNumber('id')->name('inventory.equipment.move');
        });

        Route::middleware('permission:inventory.purchasing.view|inventory.purchasing.manage')->group(function () {
            Route::get('/inventory/purchase-requests', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'requests'])->name('inventory.purchase-requests.index');
            Route::get('/inventory/purchase-orders', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'orders'])->name('inventory.purchase-orders.index');
        });
        Route::middleware('permission:inventory.purchasing.manage')->group(function () {
            Route::post('/inventory/purchase-requests', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'storeRequest'])->name('inventory.purchase-requests.store');
            Route::post('/inventory/purchase-orders', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'storeOrder'])->name('inventory.purchase-orders.store');
        });
        Route::middleware('permission:inventory.purchasing.approve')->group(function () {
            Route::post('/inventory/purchase-requests/{id}/approve', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'approveRequest'])->whereNumber('id')->name('inventory.purchase-requests.approve');
            Route::post('/inventory/purchase-orders/{id}/approve', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'approveOrder'])->whereNumber('id')->name('inventory.purchase-orders.approve');
        });
        Route::middleware('permission:inventory.receiving.manage')->post('/inventory/goods-receipts', [\App\Http\Controllers\Api\V1\Inventory\PurchasingController::class, 'receive'])->name('inventory.goods-receipts.store');

        Route::middleware('permission:inventory.equipment.sales|inventory.equipment.view')->get('/inventory/equipment-sales', [\App\Http\Controllers\Api\V1\Inventory\EquipmentSaleController::class, 'index'])->name('inventory.equipment-sales.index');
        Route::middleware('permission:inventory.equipment.sales')->group(function () {
            Route::post('/inventory/equipment-sales', [\App\Http\Controllers\Api\V1\Inventory\EquipmentSaleController::class, 'store'])->name('inventory.equipment-sales.store');
            Route::post('/inventory/equipment-sales/{id}/issue', [\App\Http\Controllers\Api\V1\Inventory\EquipmentSaleController::class, 'issue'])->whereNumber('id')->name('inventory.equipment-sales.issue');
        });

        Route::middleware('permission:inventory.reservations.manage|installations.update')->group(function () {
            Route::post('/inventory/installations/{installationId}/reserve', [\App\Http\Controllers\Api\V1\Inventory\InstallationEquipmentController::class, 'reserve'])->whereNumber('installationId')->name('inventory.installations.reserve');
            Route::post('/inventory/installation-reservations/{reservationId}/fulfill', [\App\Http\Controllers\Api\V1\Inventory\InstallationEquipmentController::class, 'fulfill'])->whereNumber('reservationId')->name('inventory.installations.fulfill');
            Route::post('/inventory/installation-equipment/install', [\App\Http\Controllers\Api\V1\Inventory\InstallationEquipmentController::class, 'install'])->name('inventory.installations.install');
            Route::post('/inventory/equipment/{equipmentId}/return-unused', [\App\Http\Controllers\Api\V1\Inventory\InstallationEquipmentController::class, 'returnUnused'])->whereNumber('equipmentId')->name('inventory.equipment.return-unused');
        });

        Route::middleware('permission:inventory.custody.manage|inventory.equipment.view')->get('/inventory/custody', [\App\Http\Controllers\Api\V1\Inventory\CustodyController::class, 'index'])->name('inventory.custody.index');
        Route::middleware('permission:inventory.custody.manage')->group(function () {
            Route::post('/inventory/custody/issue', [\App\Http\Controllers\Api\V1\Inventory\CustodyController::class, 'issue'])->name('inventory.custody.issue');
            Route::post('/inventory/custody/{id}/return', [\App\Http\Controllers\Api\V1\Inventory\CustodyController::class, 'returnItem'])->whereNumber('id')->name('inventory.custody.return');
        });

        Route::middleware('permission:inventory.repairs.manage|inventory.equipment.view')->get('/inventory/repairs', [\App\Http\Controllers\Api\V1\Inventory\RepairController::class, 'index'])->name('inventory.repairs.index');
        Route::middleware('permission:inventory.repairs.manage')->group(function () {
            Route::post('/inventory/repairs', [\App\Http\Controllers\Api\V1\Inventory\RepairController::class, 'open'])->name('inventory.repairs.open');
            Route::post('/inventory/repairs/{id}/complete', [\App\Http\Controllers\Api\V1\Inventory\RepairController::class, 'complete'])->whereNumber('id')->name('inventory.repairs.complete');
        });

        Route::middleware('permission:inventory.maintenance.manage|inventory.assets.view')->get('/inventory/maintenance-plans', [\App\Http\Controllers\Api\V1\Inventory\MaintenanceController::class, 'index'])->name('inventory.maintenance.index');
        Route::middleware('permission:inventory.maintenance.manage')->group(function () {
            Route::post('/inventory/maintenance-plans', [\App\Http\Controllers\Api\V1\Inventory\MaintenanceController::class, 'store'])->name('inventory.maintenance.store');
            Route::post('/inventory/maintenance-plans/{id}/create-task', [\App\Http\Controllers\Api\V1\Inventory\MaintenanceController::class, 'createTask'])->whereNumber('id')->name('inventory.maintenance.create-task');
        });

        Route::middleware('permission:inventory.stock.count|inventory.stock.view')->get('/inventory/stock-counts', [\App\Http\Controllers\Api\V1\Inventory\StockCountController::class, 'index'])->name('inventory.stock-counts.index');
        Route::middleware('permission:inventory.stock.count')->group(function () {
            Route::post('/inventory/stock-counts', [\App\Http\Controllers\Api\V1\Inventory\StockCountController::class, 'store'])->name('inventory.stock-counts.store');
            Route::post('/inventory/stock-counts/{id}/record', [\App\Http\Controllers\Api\V1\Inventory\StockCountController::class, 'record'])->whereNumber('id')->name('inventory.stock-counts.record');
            Route::post('/inventory/stock-counts/{id}/post', [\App\Http\Controllers\Api\V1\Inventory\StockCountController::class, 'post'])->whereNumber('id')->name('inventory.stock-counts.post');
        });

        Route::middleware('permission:inventory.dashboard.view|inventory.reports.view|dashboard.view')->get('/inventory/dashboard', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'dashboard'])->name('inventory.dashboard');
        Route::middleware('permission:inventory.products.view|inventory.equipment.view')->get('/inventory/search', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'search'])->name('inventory.search');
        Route::middleware('permission:inventory.reports.view')->group(function () {
            Route::get('/inventory/reports/balances', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'reportBalances'])->name('inventory.reports.balances');
            Route::get('/inventory/reports/transactions', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'reportTransactions'])->name('inventory.reports.transactions');
        });
        Route::middleware('permission:inventory.import')->group(function () {
            Route::post('/inventory/import/dry-run', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'importDryRun'])->name('inventory.import.dry-run');
            Route::post('/inventory/import/apply', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'importApply'])->name('inventory.import.apply');
        });
        Route::middleware('permission:inventory.equipment.view')->get('/inventory/customer-equipment', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'customerEquipmentIndex'])->name('inventory.customer-equipment.index');
        Route::middleware('permission:inventory.assets.view|inventory.assets.manage')->get('/inventory/fixed-assets', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'fixedAssetsIndex'])->name('inventory.fixed-assets.index');
        Route::middleware('permission:inventory.assets.manage')->post('/inventory/fixed-assets', [\App\Http\Controllers\Api\V1\Inventory\InventoryOpsController::class, 'fixedAssetsStore'])->name('inventory.fixed-assets.store');

        // Stage 10 / 10.2 — ISP Service Lifecycle (no Radius)
        Route::middleware('permission:services.view')->group(function () {
            Route::get('/services', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'index'])->name('services.index');
            Route::get('/services/dashboard', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'dashboard'])->name('services.dashboard');
            Route::get('/services/noc-workspace', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'noc'])->name('services.noc');
            Route::get('/services/reports/status', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'reports'])->name('services.reports.status');
            Route::get('/service-change-requests', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'changeRequests'])->name('service-change-requests.index');
            Route::get('/service-relocations', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'relocations'])->name('service-relocations.index');
            Route::get('/service-finance-holds', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'financeHolds'])->name('service-finance-holds.index');
            Route::get('/services/{id}', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'show'])->whereNumber('id')->name('services.show');
            Route::get('/services/{id}/timeline', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'timeline'])->whereNumber('id')->name('services.timeline');
            Route::get('/services/{id}/billing', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'billing'])->whereNumber('id')->name('services.billing');
            Route::get('/services/{id}/activation-checklist', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'activationChecklist'])->whereNumber('id')->name('services.activation-checklist');
            Route::get('/service-types', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'types'])->name('services.types');
            Route::get('/service-access-technologies', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'technologies'])->name('services.technologies');
            Route::get('/service-sla-templates', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'slaTemplates'])->name('services.sla-templates');
        });
        Route::middleware('permission:services.create')->post('/services', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'store'])->name('services.store');
        Route::middleware('permission:services.update')->group(function () {
            Route::put('/services/{id}', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'update'])->whereNumber('id')->name('services.update');
            Route::post('/services/{id}/transition', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'transition'])->whereNumber('id')->name('services.transition');
        });
        Route::middleware('permission:services.change')->group(function () {
            Route::post('/services/{id}/change-requests', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'changeRequest'])->whereNumber('id')->name('services.change-requests');
            Route::post('/service-change-requests/{id}/advance', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'advanceChangeRequest'])->whereNumber('id')->name('services.change-requests.advance');
        });
        Route::middleware('permission:services.relocate')->group(function () {
            Route::post('/services/{id}/relocations', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'relocate'])->whereNumber('id')->name('services.relocations');
            Route::post('/service-relocations/{id}/start', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'startRelocation'])->whereNumber('id')->name('services.relocations.start');
            Route::post('/service-relocations/{id}/complete', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'completeRelocation'])->whereNumber('id')->name('services.relocations.complete');
        });
        Route::middleware('permission:services.renew')->post('/services/{id}/renewals', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'renew'])->whereNumber('id')->name('services.renewals');
        Route::middleware('permission:services.finance_holds.manage')->group(function () {
            Route::post('/services/{id}/finance-holds', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'placeHold'])->whereNumber('id')->name('services.finance-holds');
            Route::post('/service-finance-holds/{holdId}/release', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'releaseHold'])->whereNumber('holdId')->name('services.finance-holds.release');
        });
        Route::middleware('permission:services.activate')->group(function () {
            Route::post('/services/{id}/activate', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'activate'])->whereNumber('id')->name('services.activate');
            Route::post('/services/{id}/reactivate', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'reactivate'])->whereNumber('id')->name('services.reactivate');
        });
        Route::middleware('permission:services.noc.view|services.activate')->post('/services/{id}/noc-confirm-online', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'confirmOnline'])->whereNumber('id')->name('services.noc-confirm-online');
        Route::middleware('permission:services.suspend')->post('/services/{id}/suspend', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'suspend'])->whereNumber('id')->name('services.suspend');
        Route::middleware('permission:services.cancel')->group(function () {
            Route::post('/services/{id}/cancel', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'cancel'])->whereNumber('id')->name('services.cancel');
            Route::post('/service-cancellations/{id}/approve', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'approveCancellation'])->whereNumber('id')->name('services.cancellations.approve');
            Route::post('/service-cancellations/{id}/recover-equipment', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'recoverCancellationEquipment'])->whereNumber('id')->name('services.cancellations.recover');
            Route::post('/service-cancellations/{id}/complete', [\App\Http\Controllers\Api\V1\Services\ServiceController::class, 'completeCancellation'])->whereNumber('id')->name('services.cancellations.complete');
        });

        Route::middleware('permission:services.packages.view|services.view')->get('/service-packages', [\App\Http\Controllers\Api\V1\Services\ServicePackageController::class, 'index'])->name('service-packages.index');
        Route::middleware('permission:services.packages.manage')->group(function () {
            Route::post('/service-packages', [\App\Http\Controllers\Api\V1\Services\ServicePackageController::class, 'store'])->name('service-packages.store');
            Route::post('/service-packages/{id}/versions', [\App\Http\Controllers\Api\V1\Services\ServicePackageController::class, 'storeVersion'])->whereNumber('id')->name('service-packages.versions');
        });

        Route::middleware('permission:services.locations.view|services.view')->get('/service-locations', [\App\Http\Controllers\Api\V1\Services\ServiceLocationController::class, 'index'])->name('service-locations.index');
        Route::middleware('permission:services.locations.manage|services.create')->post('/service-locations', [\App\Http\Controllers\Api\V1\Services\ServiceLocationController::class, 'store'])->name('service-locations.store');
        Route::middleware('permission:services.locations.manage|services.update')->put('/service-locations/{id}', [\App\Http\Controllers\Api\V1\Services\ServiceLocationController::class, 'update'])->whereNumber('id')->name('service-locations.update');

        Route::middleware('permission:services.contracts.view|services.view')->get('/service-contracts', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'contracts'])->name('service-contracts.index');
        Route::middleware('permission:services.contracts.manage')->group(function () {
            Route::post('/service-contracts', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'storeContract'])->name('service-contracts.store');
            Route::put('/service-contracts/{id}', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'updateContract'])->whereNumber('id')->name('service-contracts.update');
        });

        Route::middleware('permission:services.sla.manage')->group(function () {
            Route::post('/service-sla-templates', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'storeSlaTemplate'])->name('services.sla-templates.store');
            Route::put('/service-sla-templates/{id}', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'updateSlaTemplate'])->whereNumber('id')->name('services.sla-templates.update');
            Route::post('/service-sla-templates/{id}/deactivate', [\App\Http\Controllers\Api\V1\Services\ServiceCatalogController::class, 'deactivateSlaTemplate'])->whereNumber('id')->name('services.sla-templates.deactivate');
        });

        Route::middleware('permission:services.create')->post('/installations/{id}/convert-to-service', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'convertInstallation'])->whereNumber('id')->name('installations.convert-to-service');
        Route::middleware('permission:services.migrate')->group(function () {
            Route::post('/services/migration/dry-run', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'migrationDryRun'])->name('services.migration.dry-run');
            Route::post('/services/migration/apply', [\App\Http\Controllers\Api\V1\Services\ServiceOpsController::class, 'migrationApply'])->name('services.migration.apply');
        });

    });
});
