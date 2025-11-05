<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Remote Eloquent
    |--------------------------------------------------------------------------
    |
    | Enable or disable the remote eloquent functionality.
    | When disabled, all requests will return a 503 error.
    |
    */
    'enabled' => env('REMOTE_ELOQUENT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Model Namespaces
    |--------------------------------------------------------------------------
    |
    | Define the namespaces where your Eloquent models are located.
    | These are used to resolve short model names (e.g., "Post" -> "App\Models\Post")
    |
    */
    'model_namespaces' => [
        'App\\Models\\',
        'App\\',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security settings for remote queries.
    |
    */
    'security' => [
        // Strategy: whitelist, blacklist, or trait
        'strategy' => env('REMOTE_ELOQUENT_SECURITY_STRATEGY', 'whitelist'),

        // Require authentication (Laravel Sanctum)
        'require_auth' => env('REMOTE_ELOQUENT_REQUIRE_AUTH', true),

        // Rate limiting
        'rate_limiting' => [
            'enabled' => true,
            'limit' => 100, // requests per minute
        ],

        // IP whitelist (empty array = allow all)
        'ip_whitelist' => [],

        // Required trait (when strategy is 'trait')
        'required_trait' => 'RemoteEloquent\\Server\\Traits\\RemoteQueryable',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Models
    |--------------------------------------------------------------------------
    |
    | Whitelist of models that can be queried remotely.
    | Supports wildcards (e.g., "App\Models\*" allows all models in that namespace)
    | Only used when security.strategy is 'whitelist'
    |
    */
    'allowed_models' => [
        // 'Post',
        // 'Comment',
        // 'Product',
        // 'Order',
        // 'App\\Models\\*', // All models in App\Models namespace
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Models
    |--------------------------------------------------------------------------
    |
    | Blacklist of models that cannot be queried remotely.
    | Only used when security.strategy is 'blacklist'
    |
    */
    'blocked_models' => [
        'User',
        'Password',
        'PersonalAccessToken',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Define which Eloquent methods can be called remotely.
    | This is a critical security layer.
    |
    */
    'allowed_methods' => [
        'chain' => [
            // Where clauses
            'where', 'orWhere', 'whereIn', 'whereNotIn', 'whereBetween', 'whereNotBetween',
            'whereNull', 'whereNotNull', 'whereDate', 'whereMonth', 'whereDay', 'whereYear',
            'whereTime', 'whereColumn', 'whereLike',

            // Relationships
            'with', 'withCount', 'withSum', 'withAvg', 'withMin', 'withMax',
            'has', 'orHas', 'doesntHave', 'orDoesntHave',
            'whereHas', 'orWhereHas', 'whereDoesntHave', 'orWhereDoesntHave',

            // Ordering
            'orderBy', 'orderByDesc', 'latest', 'oldest', 'inRandomOrder',

            // Limiting
            'limit', 'take', 'skip', 'offset',

            // Selecting
            'select', 'addSelect', 'distinct',

            // Grouping
            'groupBy', 'having',

            // Joins
            'join', 'leftJoin', 'rightJoin',

            // Scopes
            'withoutGlobalScope', 'withoutGlobalScopes',
        ],

        'terminal' => [
            // Reading
            'get', 'first', 'find', 'findOrFail', 'sole',
            'value', 'pluck',
            'count', 'sum', 'avg', 'average', 'max', 'min',
            'exists', 'doesntExist',

            // Pagination
            'paginate', 'simplePaginate', 'cursorPaginate',

            // Writing (DISABLED by default - enable only if you understand the risks!)
            // 'create', 'insert', 'update', 'delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Row Level Security
    |--------------------------------------------------------------------------
    |
    | Global scopes are automatically applied to all queries.
    | This provides row-level security for multi-tenancy and user isolation.
    |
    */
    'row_level_security' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Limits
    |--------------------------------------------------------------------------
    |
    | Set limits on query execution to prevent abuse.
    |
    */
    'limits' => [
        // Maximum records per query
        'max_limit' => 1000,

        // Maximum execution time (seconds)
        'max_execution_time' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging of remote queries.
    |
    */
    'logging' => [
        'enabled' => env('REMOTE_ELOQUENT_LOGGING', true),
        'log_requests' => false,
        'log_responses' => false,
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // milliseconds
    ],

];
