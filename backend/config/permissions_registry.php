<?php

/**
 * Canonical permission names and aliases for Stage 10+ service lifecycle.
 * Prefer RolePermissionSeeder / Spatie names; aliases exist for legacy callers.
 */
return [
    'permissions' => [
        'services.view',
        'services.create',
        'services.update',
        'services.activate',
        'services.suspend',
        'services.reactivate',
        'services.cancel',
        'services.change',
        'services.relocate',
        'services.renew',
        'services.packages.view',
        'services.packages.manage',
        'services.locations.view',
        'services.locations.manage',
        'services.contracts.view',
        'services.contracts.manage',
        'services.finance_holds.manage',
        'services.billing.view',
        'services.sla.manage',
        'services.dashboard.view',
        'services.noc.view',
        'services.reports.view',
        'services.migrate',
        'services.types.manage',
    ],

    /**
     * Alias => canonical Spatie permission name.
     */
    'aliases' => [
        'services.change_package' => 'services.change',
        'services.manage_finance_hold' => 'services.finance_holds.manage',
        'finance_holds' => 'services.finance_holds.manage',
        'services.finance_holds' => 'services.finance_holds.manage',
    ],
];
