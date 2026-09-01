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

    'internal_secret' => env('INTERNAL_SERVICE_SECRET', ''),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'hunter' => [
        'api_key' => env('HUNTER_API_KEY', ''),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY', ''),
        'from_email' => env('SENDGRID_FROM_EMAIL', 'outreach@tassproject.test'),
        'from_name' => env('SENDGRID_FROM_NAME', 'TassProject Outreach'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'from_number' => env('TWILIO_FROM_NUMBER', ''),
    ],

    'google' => [
        'places_api_key' => env('GOOGLE_PLACES_API_KEY', ''),
    ],

    'powerbi' => [
        'workspace_id' => env('POWERBI_WORKSPACE_ID', ''),
        'tenant_id' => env('POWERBI_TENANT_ID', ''),
        'client_id' => env('POWERBI_CLIENT_ID', ''),
        'client_secret' => env('POWERBI_CLIENT_SECRET', ''),
        'report_id_campaign' => env('POWERBI_REPORT_ID_CAMPAIGN', ''),
        'report_id_leadfinder' => env('POWERBI_REPORT_ID_LEADFINDER', ''),
        'report_id_pipeline' => env('POWERBI_REPORT_ID_PIPELINE', ''),
    ],

];
