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
    | Authentication Required (Server Mode)
    |--------------------------------------------------------------------------
    |
    | Require Laravel Sanctum authentication for API requests.
    |
    */
    'require_auth' => env('REMOTE_ELOQUENT_REQUIRE_AUTH', true),

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

];
