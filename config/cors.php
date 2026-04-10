<?php

return [
    'paths' => [
        'crm-store-lead',
        'lead-capture',
        'api/lead-capture',
    ],

    'allowed_methods' => ['POST', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1:8000'))
    ))),

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Lead-Capture-Token',
    ],

    'supports_credentials' => false,
];
