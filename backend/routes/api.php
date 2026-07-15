<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');
    });

    Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {
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
    });
});
