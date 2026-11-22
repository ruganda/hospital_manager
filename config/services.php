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

    // Mobile Money (MTN / Airtel) via Flutterwave. Driver "fake" simulates payments with no keys (demo / local).
    'mobile_money' => [
        'driver' => env('MOBILE_MONEY_DRIVER', 'fake'), // fake | flutterwave
    ],

    'flutterwave' => [
        'secret_key' => env('FLW_SECRET_KEY'),           // dashboard → Settings → API keys (test keys start FLWSECK_TEST-)
        'secret_hash' => env('FLW_SECRET_HASH'),         // dashboard → Settings → Webhooks → "Secret hash" (any string you choose)
        'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
        'timeout' => (int) env('FLW_TIMEOUT', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
