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
            // note: no zoho.configure (secrets / OAuth)
        ]);

        $branchManager->syncPermissions([
            'users.view',
            'branches.view',
            'dashboard.view',
            'customers.view',
            'invoices.view',
            'debtors.view',
        ]);

        $collector->syncPermissions([
            'branches.view',
            'dashboard.view',
            // Stage 2: collectors do NOT get customers.view / debtors.view yet
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
        ]);
    }
}
