<?php

return [
    'enabled' => env('CRM_ENABLED', true),

    'lead_number' => [
        'sequence_pad' => 6,
        'infix' => 'LEAD',
    ],

    'quote_number' => [
        'sequence_pad' => 6,
        'infix' => 'QTE',
    ],

    'follow_up' => [
        'stale_hours' => 24,
        'evaluate_batch' => 200,
    ],
];
