<?php

/**
 * Configuration Examples
 */

// ============================================
// CLIENT CONFIGURATION (Mobile App)
// config/remote-eloquent.php
// ============================================

return [
    // Backend API URL
    'api_url' => env('REMOTE_ELOQUENT_API_URL', 'https://api.yourapp.com'),

    // Authentication configuration
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

    // HTTP request configuration
    'request' => [
        'timeout' => (int) env('REMOTE_ELOQUENT_TIMEOUT', 30),
        'retry' => [
            'enabled' => true,
            'times' => 3,
            'sleep' => 100, // milliseconds
        ],
    ],

    // Caching
    'cache' => [
        'enabled' => env('REMOTE_ELOQUENT_CACHE_ENABLED', false),
        'ttl' => 60, // seconds
    ],
];

// ============================================
// SERVER CONFIGURATION (Backend API)
// config/remote-eloquent.php
// ============================================

return [
    // Enable/disable remote eloquent
    'enabled' => env('REMOTE_ELOQUENT_ENABLED', true),

    // Model namespaces for resolution
    'model_namespaces' => [
        'App\\Models\\',
        'App\\',
    ],

    // Security configuration
    'security' => [
        // Strategy: whitelist, blacklist, or trait
        'strategy' => env('REMOTE_ELOQUENT_SECURITY_STRATEGY', 'whitelist'),

        // Require authentication (Laravel Sanctum)
        'require_auth' => env('REMOTE_ELOQUENT_REQUIRE_AUTH', true),

        // Rate limiting
        'rate_limiting' => [
            'enabled' => true,
            'limit' => 100, // requests per minute per user
        ],

        // IP whitelist (empty = allow all)
        'ip_whitelist' => [
            // '192.168.1.100',
            // '10.0.0.50',
        ],

        // Required trait (when strategy is 'trait')
        'required_trait' => 'RemoteEloquent\\Server\\Traits\\RemoteQueryable',
    ],

    // Allowed models (when strategy is 'whitelist')
    'allowed_models' => [
        'Post',
        'Comment',
        'Product',
        'Order',
        'Category',
        'App\\Models\\*', // All models in App\Models namespace
    ],

    // Blocked models (when strategy is 'blacklist')
    'blocked_models' => [
        'User',
        'Password',
        'PersonalAccessToken',
        'PasswordResetToken',
    ],

    // Allowed methods
    'allowed_methods' => [
        'chain' => [
            // Where clauses
            'where', 'orWhere', 'whereIn', 'whereNotIn',
            'whereBetween', 'whereNotBetween',
            'whereNull', 'whereNotNull',
            'whereDate', 'whereMonth', 'whereDay', 'whereYear', 'whereTime',
            'whereColumn', 'whereLike',

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

            // Writing (DISABLED by default!)
            // Uncomment only if you understand the security implications
            // 'create', 'insert', 'update', 'delete',
        ],
    ],

    // Query limits
    'limits' => [
        'max_limit' => 1000, // Maximum records per query
        'max_execution_time' => 30, // seconds
    ],

    // Logging configuration
    'logging' => [
        'enabled' => env('REMOTE_ELOQUENT_LOGGING', true),
        'log_requests' => false,
        'log_responses' => false,
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // milliseconds
    ],
];

// ============================================
// ENVIRONMENT VARIABLES (.env)
// ============================================

// Client (Mobile App)
// REMOTE_ELOQUENT_API_URL=https://api.yourapp.com
// REMOTE_ELOQUENT_TOKEN=your-sanctum-token

// Server (Backend API)
// REMOTE_ELOQUENT_ENABLED=true
// REMOTE_ELOQUENT_REQUIRE_AUTH=true
// REMOTE_ELOQUENT_SECURITY_STRATEGY=whitelist
// REMOTE_ELOQUENT_LOGGING=true
