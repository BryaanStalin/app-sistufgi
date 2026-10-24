<?php

return [

    'powerbi_api' => [
        'enabled' => (bool) env('POWERBI_API_ENABLED', false),
        'user' => env('POWERBI_API_USER', ''),
        'password' => env('POWERBI_API_PASSWORD', ''),
        'key' => env('POWERBI_API_KEY', ''),
        'require_https' => (bool) env('POWERBI_API_REQUIRE_HTTPS', true),
    ],

    'dni_api' => [
        'url' => env('DNI_API_URL', env('URL_API_CONSULTA_DNI')),
        'token' => env('DNI_API_TOKEN', env('API_KEY_CONSULTA_DNI')),
        'timeout' => (int) env('DNI_API_TIMEOUT', 8),
        'enabled' => (bool) env('DNI_API_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
