<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'meta' => [
        'whatsapp_token'            => env('META_WHATSAPP_TOKEN', ''),
        'whatsapp_phone_id'         => env('META_WHATSAPP_PHONE_NUMBER_ID', ''),
        'whatsapp_verify_token'     => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'crm_verify_token'),
        'whatsapp_default_template'          => env('META_WHATSAPP_DEFAULT_TEMPLATE', 'hello_world'),
        'whatsapp_default_template_language' => env('META_WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'en'),
    ],

    'geo_lookup' => [
        'base_url' => env('GEO_LOOKUP_BASE_URL', 'https://ipwho.is'),
    ],

];
