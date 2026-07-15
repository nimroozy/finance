<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DebtorController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\Zoho\ZohoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

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
            Route::get('/zoho/organizations', [ZohoController::class, 'organizations'])->name('zoho.organizations');
            Route::post('/zoho/test', [ZohoController::class, 'test'])->name('zoho.test');
            Route::get('/zoho/sync-jobs', [ZohoController::class, 'syncJobs'])->name('zoho.sync-jobs.index');
            Route::get('/zoho/sync-jobs/{id}', [ZohoController::class, 'syncJob'])
                ->whereNumber('id')
                ->name('zoho.sync-jobs.show');
            Route::get('/zoho/api-logs', [ZohoController::class, 'apiLogs'])->name('zoho.api-logs');
        });

        Route::middleware('permission:zoho.configure')->group(function () {
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
        });

        Route::middleware('permission:zoho.sync')->group(function () {
            Route::post('/zoho/sync/{type}', [ZohoController::class, 'sync'])
                ->where('type', 'customers|invoices|full')
                ->name('zoho.sync');
            Route::post('/zoho/sync-jobs/{id}/retry', [ZohoController::class, 'retrySyncJob'])
                ->whereNumber('id')
                ->name('zoho.sync-jobs.retry');
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
    });
});
