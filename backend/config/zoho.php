<?php

return [

    'client_id' => env('ZOHO_CLIENT_ID'),

    'client_secret' => env('ZOHO_CLIENT_SECRET'),

    'redirect_uri' => env('ZOHO_REDIRECT_URI', env('APP_URL').'/api/v1/zoho/oauth/callback'),

    'organization_id' => env('ZOHO_ORGANIZATION_ID'),

    'accounts_domain' => env('ZOHO_ACCOUNTS_DOMAIN', 'https://accounts.zoho.com'),

    'api_domain' => env('ZOHO_API_DOMAIN', 'https://www.zohoapis.com'),

    /*
    |--------------------------------------------------------------------------
    | Data center presets
    |--------------------------------------------------------------------------
    |
    | Map of known Zoho data centers to their accounts + Books API hosts.
    |
    */
    'data_centers' => [
        'us' => [
            'accounts' => 'https://accounts.zoho.com',
            'api' => 'https://www.zohoapis.com',
            'label' => 'United States',
        ],
        'eu' => [
            'accounts' => 'https://accounts.zoho.eu',
            'api' => 'https://www.zohoapis.eu',
            'label' => 'Europe',
        ],
        'in' => [
            'accounts' => 'https://accounts.zoho.in',
            'api' => 'https://www.zohoapis.in',
            'label' => 'India',
        ],
        'au' => [
            'accounts' => 'https://accounts.zoho.com.au',
            'api' => 'https://www.zohoapis.com.au',
            'label' => 'Australia',
        ],
        'ca' => [
            'accounts' => 'https://accounts.zohocloud.ca',
            'api' => 'https://www.zohoapis.ca',
            'label' => 'Canada',
        ],
        'sa' => [
            'accounts' => 'https://accounts.zoho.sa',
            'api' => 'https://www.zohoapis.sa',
            'label' => 'Saudi Arabia',
        ],
    ],

    'default_data_center' => env('ZOHO_DATA_CENTER', 'us'),

    'oauth_scopes' => env(
        'ZOHO_OAUTH_SCOPES',
        'ZohoBooks.fullaccess.all'
    ),

    'token_refresh_buffer_seconds' => (int) env('ZOHO_TOKEN_REFRESH_BUFFER', 120),

    'http' => [
        'timeout' => (int) env('ZOHO_HTTP_TIMEOUT', 30),
        'retries' => (int) env('ZOHO_HTTP_RETRIES', 3),
        'retry_delay_ms' => (int) env('ZOHO_HTTP_RETRY_DELAY_MS', 500),
        'per_page' => (int) env('ZOHO_PER_PAGE', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler intervals (minutes) — overridable via system_settings keys
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'customer_sync_minutes' => (int) env('ZOHO_CUSTOMER_SYNC_MINUTES', 60),
        'invoice_sync_minutes' => (int) env('ZOHO_INVOICE_SYNC_MINUTES', 60),
        'token_refresh_minutes' => (int) env('ZOHO_TOKEN_REFRESH_MINUTES', 30),
        'retry_failed_minutes' => (int) env('ZOHO_RETRY_FAILED_MINUTES', 15),
    ],

    'frontend_connected_path' => '/en/zoho?connected=1',

    /*
    |--------------------------------------------------------------------------
    | Customer payments push (Stage 4)
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'dry_run' => (bool) env('ZOHO_PAYMENT_DRY_RUN', false),
        'endpoint' => env('ZOHO_PAYMENT_ENDPOINT', 'customerpayments'),
        // Deposit / undeposited funds account used when creating customer payments.
        'default_account_id' => env('ZOHO_PAYMENT_ACCOUNT_ID'),
        'default_account_name' => env('ZOHO_PAYMENT_ACCOUNT_NAME', 'Undeposited Funds'),
    ],

];