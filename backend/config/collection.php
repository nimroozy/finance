<?php

return [
    'gps' => [
        'warning_meters' => (int) env('COLLECTION_GPS_WARNING_METERS', 200),
        'high_risk_meters' => (int) env('COLLECTION_GPS_HIGH_RISK_METERS', 1000),
    ],

    'map' => [
        // leaflet | google (google requires MAP_GOOGLE_API_KEY)
        'provider' => env('COLLECTION_MAP_PROVIDER', 'leaflet'),
        'google_api_key' => env('MAP_GOOGLE_API_KEY'),
        'tile_url' => env('COLLECTION_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; OpenStreetMap contributors',
    ],

    'visit_edit_grace_minutes' => (int) env('COLLECTION_VISIT_EDIT_GRACE_MINUTES', 30),

    'bulk_assignment_sync_threshold' => (int) env('COLLECTION_BULK_SYNC_THRESHOLD', 50),

    'promise' => [
        'due_soon_days' => (int) env('COLLECTION_PROMISE_DUE_SOON_DAYS', 3),
        'max_active_per_customer' => (int) env('COLLECTION_PROMISE_MAX_ACTIVE', 1),
    ],
];
