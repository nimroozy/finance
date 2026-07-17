<?php

/**
 * Platform module flags (backend mirror of frontend feature-flags).
 * Unfinished modules must stay disabled until their stage ships.
 */
return [
    'modules' => [
        'whatsapp' => ['enabled' => true, 'stage' => 6],
        'ticketing' => ['enabled' => false, 'stage' => 7],
        'tasks' => ['enabled' => false, 'stage' => 7],
        'crm' => ['enabled' => false, 'stage' => 8],
        'installations' => ['enabled' => false, 'stage' => 8],
        'inventory' => ['enabled' => false, 'stage' => 9],
        'assets' => ['enabled' => false, 'stage' => 9],
        'sites' => ['enabled' => false, 'stage' => 9],
        'radius' => ['enabled' => false, 'stage' => 10],
        'purchasing' => ['enabled' => false, 'stage' => 11],
        'unified_dashboards' => ['enabled' => false, 'stage' => 11],
    ],
];
