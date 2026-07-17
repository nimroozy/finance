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

    'activation_checklist' => [
        'customer_confirmed',
        'equipment_installed',
        'link_tested',
        'documentation_complete',
        // Radius provisioning intentionally omitted — Stage 10 is service lifecycle only.
    ],

    'auto_draft_on_installation_complete' => env('SERVICE_AUTO_DRAFT_ON_INSTALL_COMPLETE', true),

    'radius' => [
        // Hard rule: Stage 10 must NOT connect to SAS Radius.
        'enabled' => false,
    ],
];
