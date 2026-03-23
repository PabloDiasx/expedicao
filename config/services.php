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

    'nomus' => [
        'location' => env('NOMUS_LOCATION'),
        'integration_key' => env('NOMUS_INTEGRATION_KEY'),
        'verify_ssl' => filter_var(env('NOMUS_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
        'timeout_seconds' => (int) env('NOMUS_TIMEOUT_SECONDS', 30),
        'initial_lookback_days' => (int) env('NOMUS_INITIAL_LOOKBACK_DAYS', 30),
        'sync_overlap_minutes' => (int) env('NOMUS_SYNC_OVERLAP_MINUTES', 2),
        'sync_minutes' => (int) env('NOMUS_SYNC_MINUTES', 5),
    ],

];
