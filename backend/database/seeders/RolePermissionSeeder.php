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
        'users.view',
        'users.manage',
        'roles.view',
        'branches.view',
        'branches.manage',
        'audit.view',
        'settings.manage',
        'dashboard.view',
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
        ]);

        $branchManager->syncPermissions([
            'users.view',
            'branches.view',
            'dashboard.view',
        ]);

        $collector->syncPermissions([
            'branches.view',
            'dashboard.view',
        ]);

        $auditor->syncPermissions([
            'users.view',
            'roles.view',
            'branches.view',
            'audit.view',
            'dashboard.view',
        ]);
    }
}
