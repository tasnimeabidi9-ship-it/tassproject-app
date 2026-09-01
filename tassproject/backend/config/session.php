<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Defaults
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the session / cookie configuration values.
    | The values are merged into the runtime configuration and merged when
    | the session middleware is executed. We'll merge them here to give
    | precedence to these values over the values in the configuration.
    |
    */

    'defaults' => [
        'guard' => env('SESSION_GUARD', 'web'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => env('SESSION_ENCRYPT', false),
        'files' => storage_path('framework/sessions'),
        'connection' => env('SESSION_CONNECTION'),
        'table' => env('SESSION_TABLE', 'sessions'),
        'store' => env('SESSION_STORE'),
        'lottery' => [2, 100],
        'cookie' => env(
            'SESSION_COOKIE',
            'leadcrm_ai_session'
        ),
        'path' => env('SESSION_PATH', '/'),
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('SESSION_SECURE_COOKIE'),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'lax'),
        'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | cache store that should be used to store the session data between
    | requests. This must match one of your defined cache stores.
    |
    | Affects: session缓存
    |
    */

    'cache_store' => env('SESSION_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the locations that will be
    | swept by default since they are set to always be swept.
    |
    */

    'sweep' => [
        storage_path('framework/sessions'),
    ],

];
