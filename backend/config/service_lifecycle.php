<?php

return [
    'enabled' => env('SERVICE_LIFECYCLE_ENABLED', true),

    'service_number' => [
        'sequence_pad' => 6,
        'infix' => 'SVC',
    ],

    'contract_number' => [
        'sequence_pad' => 6,
        'infix' => 'CTR',
    ],

    // Evidence-backed activation checklist (evaluated from DB — never client auto-true).
    'activation_checklist' => [
        'zoho_linked',
        'installation_completed',
        'inventory_reconciled',
        'equipment_assigned',
    ],

    'auto_draft_on_installation_complete' => env('SERVICE_AUTO_DRAFT_ON_INSTALL_COMPLETE', true),

    'radius' => [
        // Hard rule: Stage 10 must NOT connect to SAS Radius.
        'enabled' => false,
    ],
];
