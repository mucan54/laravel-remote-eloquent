<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | 'client' - NativePHP mobile app (sends queries to remote API)
    | 'server' - Backend API (executes queries on local database)
    |
    */
    'mode' => env('REMOTE_ELOQUENT_MODE', 'server'),

    /*
    |--------------------------------------------------------------------------
    | API URL (Client Mode Only)
    |--------------------------------------------------------------------------
    |
    | The backend API URL when running in client mode.
    |
    */
    'api_url' => env('REMOTE_ELOQUENT_API_URL', 'https://api.yourapp.com'),

    /*
    |--------------------------------------------------------------------------
    | Authentication (Client Mode)
    |--------------------------------------------------------------------------
    |
    | Configure how the client authenticates with the backend.
    |
    */
    'auth' => [
        'cache_key' => 'remote_eloquent_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Middleware (Server Mode)
    |--------------------------------------------------------------------------
    |
    | Specify the authentication middleware for API requests.
    |
    | Default: 'auth:sanctum' (Laravel Sanctum - recommended)
    |
    | Examples:
    | - 'auth:sanctum'     - Laravel Sanctum (default, best for SPAs/mobile)
    | - 'auth:api'         - Laravel Passport
    | - 'jwt.auth'         - JWT Auth
    | - null               - Disable authentication (NOT recommended for production)
    | - ['auth:sanctum', 'throttle:100,1'] - Multiple middleware
    |
    */
    'auth_middleware' => env('REMOTE_ELOQUENT_AUTH_MIDDLEWARE', 'auth:sanctum'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Models (Server Mode)
    |--------------------------------------------------------------------------
    |
    | Whitelist of models that can be queried remotely.
    | IMPORTANT: Configure this for security!
    |
    */
    'allowed_models' => [
        // 'Post',
        // 'Comment',
        // 'Product',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Whitelist of allowed Eloquent methods.
    |
    */
    'allowed_methods' => [
        'chain' => [
            'where', 'orWhere', 'whereIn', 'whereNotIn', 'whereBetween',
            'whereNull', 'whereNotNull', 'whereDate', 'whereColumn',
            'with', 'withCount', 'has', 'whereHas', 'doesntHave',
            'orderBy', 'orderByDesc', 'latest', 'oldest',
            'limit', 'take', 'skip', 'offset',
            'select', 'addSelect', 'distinct',
            'groupBy', 'having',
        ],
        'terminal' => [
            'get', 'first', 'find', 'findOrFail',
            'count', 'sum', 'avg', 'max', 'min',
            'exists', 'doesntExist',
            'pluck', 'value',
            'paginate', 'simplePaginate',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Queries
    |--------------------------------------------------------------------------
    |
    | Configure batch query execution to improve performance.
    |
    */
    'batch' => [
        'enabled' => env('REMOTE_ELOQUENT_BATCH_ENABLED', true),
        'max_queries' => env('REMOTE_ELOQUENT_BATCH_MAX', 10),
    ],

];
