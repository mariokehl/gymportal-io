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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paddle' => [
        'token' => env('PADDLE_TOKEN'),
        'price_id' => env('PADDLE_PRICE_ID'),
        'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'), // sandbox oder production
    ],

    'apple_wallet' => [
        'certificate_base64' => env('APPLE_WALLET_CERTIFICATE_BASE64'),
        'certificate_password' => env('APPLE_WALLET_CERTIFICATE_PASSWORD'),
        'pass_type_identifier' => env('APPLE_WALLET_PASS_TYPE_IDENTIFIER'),
        'team_identifier' => env('APPLE_WALLET_TEAM_IDENTIFIER'),
    ],

    'google_wallet' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'service_account_base64' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_BASE64'),
    ],

];
