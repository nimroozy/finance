<?php

/**
 * Platform module flags (backend mirror of frontend feature-flags).
 * Unfinished modules must stay disabled until their stage ships.
 */
return [
    'modules' => [
        'whatsapp' => ['enabled' => true, 'stage' => 6],
        'ticketing' => ['enabled' => true, 'stage' => 7],
        'tasks' => ['enabled' => true, 'stage' => 7],
        'crm' => ['enabled' => true, 'stage' => 8],
        'installations' => ['enabled' => true, 'stage' => 8],
        'inventory' => ['enabled' => true, 'stage' => 9],
        'assets' => ['enabled' => true, 'stage' => 9],
        'sites' => ['enabled' => true, 'stage' => 9],
        'service_lifecycle' => ['enabled' => true, 'stage' => 10],
        // Radius remains deferred — Stage 10 is Service Lifecycle per product brief.
        'radius' => ['enabled' => false, 'stage' => 12],
        'purchasing' => ['enabled' => false, 'stage' => 11],
        'unified_dashboards' => ['enabled' => false, 'stage' => 11],
    ],
];
