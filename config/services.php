<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | GreenBite 第三方服务（Sprint 1）
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => 8,
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_version' => '2024-06-20',
    ],

    'payme' => [
        'merchant_id' => env('PAYME_MERCHANT_ID'),
        'api_key' => env('PAYME_API_KEY'),
        'webhook_secret' => env('PAYME_WEBHOOK_SECRET'),
    ],
];
