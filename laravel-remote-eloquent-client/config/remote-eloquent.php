<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote API URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your Laravel backend API. All remote queries will be
    | sent to this endpoint. Don't include trailing slash.
    |
    */
    'api_url' => env('REMOTE_ELOQUENT_API_URL', 'https://api.yourapp.com'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the client authenticates with the remote backend.
    | Supported drivers: cache, session, config
    |
    */
    'auth' => [
        // Driver: cache, session, or config
        'driver' => env('REMOTE_ELOQUENT_AUTH_DRIVER', 'cache'),

        // Cache key (when driver is 'cache')
        'cache_key' => 'remote_eloquent_token',

        // Session key (when driver is 'session')
        'session_key' => 'remote_eloquent_token',

        // Token (when driver is 'config')
        'token' => env('REMOTE_ELOQUENT_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Configuration
    |--------------------------------------------------------------------------
    |
    | Configure timeout and retry behavior for HTTP requests.
    |
    */
    'request' => [
        // Timeout in seconds
        'timeout' => (int) env('REMOTE_ELOQUENT_TIMEOUT', 30),

        // Retry configuration
        'retry' => [
            'enabled' => true,
            'times' => 3,
            'sleep' => 100, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching of query results to reduce API calls.
    |
    */
    'cache' => [
        'enabled' => env('REMOTE_ELOQUENT_CACHE_ENABLED', false),
        'ttl' => 60, // seconds
        'driver' => env('REMOTE_ELOQUENT_CACHE_DRIVER', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Enable logging of remote queries for debugging.
    |
    */
    'logging' => [
        'enabled' => env('REMOTE_ELOQUENT_LOGGING', false),
        'channel' => env('REMOTE_ELOQUENT_LOG_CHANNEL', 'stack'),
    ],

];
