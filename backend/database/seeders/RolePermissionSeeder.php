<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        // Stage 1
        'users.view',
        'users.manage',
        'roles.view',
        'branches.view',
        'branches.manage',
        'audit.view',
        'settings.manage',
        'dashboard.view',
        // Stage 2 — Zoho / customers / invoices / debtors
        'zoho.configure',
        'zoho.view',
        'zoho.sync',
        'customers.view',
        'customers.manage',
        'invoices.view',
        'debtors.view',
        'debtors.export',
        // Stage 3 — assignments / visits / routes / promises / notes / evidence / reports
        'assignments.view',
        'assignments.manage',
        'assignments.reassign',
        'assignments.cancel',
        'assignments.export',
        'visits.view',
        'visits.create',
        'visits.manage',
        'routes.view',
        'routes.manage',
        'promises.view',
        'promises.create',
        'promises.manage',
        'notes.view',
        'notes.create',
        'notes.manage',
        'evidence.view',
        'evidence.upload',
        'notifications.view',
        'reports.assignments',
        'reports.visits',
        'reports.promises',
        'collectors.view',
        'collectors.manage',
        'escalations.view',
        'escalations.manage',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web');
        $centralFinance = Role::findOrCreate(User::ROLE_CENTRAL_FINANCE, 'web');
        $branchManager = Role::findOrCreate(User::ROLE_BRANCH_MANAGER, 'web');
        $collector = Role::findOrCreate(User::ROLE_COLLECTOR, 'web');
        $auditor = Role::findOrCreate(User::ROLE_AUDITOR, 'web');

        $superAdmin->syncPermissions(self::PERMISSIONS);

        $centralFinance->syncPermissions([
            'users.view',
            'users.manage',
            'roles.view',
            'branches.view',
            'branches.manage',
            'audit.view',
            'settings.manage',
            'dashboard.view',
            'zoho.view',
            'zoho.sync',
            'customers.view',
            'customers.manage',
            'invoices.view',
            'debtors.view',
            'debtors.export',
            'assignments.view',
            'assignments.manage',
            'assignments.reassign',
            'assignments.export',
            'visits.view',
            'routes.view',
            'promises.view',
            'promises.manage',
            'notes.view',
            'evidence.view',
            'notifications.view',
            'reports.assignments',
            'reports.visits',
            'reports.promises',
            'collectors.view',
            'escalations.view',
        ]);

        $branchManager->syncPermissions([
            'users.view',
            'branches.view',
            'dashboard.view',
            'customers.view',
            'invoices.view',
            'debtors.view',
            'assignments.view',
            'assignments.manage',
            'assignments.reassign',
            'assignments.cancel',
            'assignments.export',
            'visits.view',
            'visits.manage',
            'routes.view',
            'routes.manage',
            'promises.view',
            'promises.manage',
            'notes.view',
            'notes.create',
            'notes.manage',
            'evidence.view',
            'notifications.view',
            'reports.assignments',
            'reports.visits',
            'reports.promises',
            'collectors.view',
            'escalations.view',
            'escalations.manage',
        ]);

        $collector->syncPermissions([
            'branches.view',
            'dashboard.view',
            'customers.view',
            'invoices.view',
            'assignments.view',
            'visits.view',
            'visits.create',
            'routes.view',
            'promises.view',
            'promises.create',
            'notes.view',
            'notes.create',
            'evidence.view',
            'evidence.upload',
            'notifications.view',
        ]);

        $auditor->syncPermissions([
            'users.view',
            'roles.view',
            'branches.view',
            'audit.view',
            'dashboard.view',
            'zoho.view',
            'customers.view',
            'invoices.view',
            'debtors.view',
            'assignments.view',
            'visits.view',
            'routes.view',
            'promises.view',
            'notes.view',
            'evidence.view',
            'reports.assignments',
            'reports.visits',
            'reports.promises',
            'collectors.view',
            'escalations.view',
        ]);
    }
}
