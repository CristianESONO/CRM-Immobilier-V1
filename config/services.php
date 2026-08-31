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

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN', 'mock_token'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', 'mock_phone_number_id'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'crm_whatsapp_verify_token_2026'),
    ],

    'openwa' => [
        'url' => env('OPENWA_API_URL', 'https://mywa.tickets-place.net/api'),
        'key' => env('OPENWA_API_KEY', 'owa_k1_e05c74e13e2a679eae14d957458d798979f5a780c3fe36e76284969fd8c3c4b0'),
        'session_id' => env('OPENWA_SESSION_ID', '1b9201d2-932d-4cae-8b5f-c58c1d9780a1'),
    ],

];
