<?php

return [
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    'default_country' => env('WHATSAPP_DEFAULT_COUNTRY', 'AF'),
    'outgoing_paused' => env('WHATSAPP_OUTGOING_PAUSED', false),
];
