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

    // Добавьте в конец файла
'gigachat' => [
    'client_id' => env('GIGACHAT_CLIENT_ID'),
    'secret' => env('GIGACHAT_SECRET'),
    'scope' => env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
    'auth_url' => env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443'),
    'api_url' => env('GIGACHAT_API_URL', 'https://gigachat.devices.sberbank.ru/api/v1'),
    'model' => env('GIGACHAT_MODEL', 'GigaChat'),
    'verify_ssl' => filter_var(env('GIGACHAT_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
],

'storage' => [
    'url' => env('STORAGE_SERVICE_URL', 'http://localhost:8001'),
    'mock' => env('STORAGE_SERVICE_MOCK', true),
],

];
