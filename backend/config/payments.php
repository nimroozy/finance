<?php

return [
    'draft_expiry_hours' => (int) env('PAYMENT_DRAFT_EXPIRY_HOURS', 24),

    'max_amount' => env('PAYMENT_MAX_AMOUNT', '100000000.0000'),

    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'AFN'),

    'idempotency_ttl_hours' => (int) env('PAYMENT_IDEMPOTENCY_TTL_HOURS', 48),

    'receipt' => [
        'template_version' => env('PAYMENT_RECEIPT_TEMPLATE_VERSION', '1.0'),
        'default_language' => env('PAYMENT_RECEIPT_LANGUAGE', 'en'),
        'sequence_pad' => 6,
    ],

    // Reuse collection GPS thresholds where possible (see config/collection.php).
    'gps' => [
        'warning_meters' => (int) env(
            'PAYMENT_GPS_WARNING_METERS',
            env('COLLECTION_GPS_WARNING_METERS', 200)
        ),
        'high_risk_meters' => (int) env(
            'PAYMENT_GPS_HIGH_RISK_METERS',
            env('COLLECTION_GPS_HIGH_RISK_METERS', 1000)
        ),
    ],

    'wallet' => [
        'enabled' => (bool) env('PAYMENT_WALLET_ENABLED', true),
    ],

    'reconciliation' => [
        'daily_enabled' => (bool) env('PAYMENT_RECONCILIATION_DAILY', true),
    ],
];
