<?php

return [
    'paths' => ['api/lead-capture', 'crm-store-lead'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'], // For testing. Later restrict domain

    'allowed_headers' => ['*'],

    'supports_credentials' => false,
];
