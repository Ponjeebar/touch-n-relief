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
        'from_address' => env('RESEND_FROM_ADDRESS'),
        'from_name' => env('RESEND_FROM_NAME', env('APP_NAME', 'TouchNRelief')),
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

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'payment_method_types' => array_values(array_filter(
            preg_split('/[\s,]+/', strtolower(trim((string) env('PAYMONGO_PAYMENT_METHOD_TYPES', 'gcash,qrph')))) ?: []
        )),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'google_drive_backup' => [
        'enabled' => filter_var(env('GOOGLE_DRIVE_BACKUP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'client_id' => env('GOOGLE_DRIVE_BACKUP_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_BACKUP_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_BACKUP_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
        'retention_days' => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_DAYS', 14),
    ],

];
