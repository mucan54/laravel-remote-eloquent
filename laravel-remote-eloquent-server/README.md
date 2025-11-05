# Laravel Remote Eloquent - Server Package

Server package for executing remote Eloquent queries with security and validation.

## Installation

```bash
composer require mucan54/laravel-remote-eloquent-server
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=remote-eloquent-config
```

Configure in `config/remote-eloquent.php`:

```php
return [
    'enabled' => true,

    'security' => [
        'strategy' => 'whitelist',
        'require_auth' => true,
    ],

    'allowed_models' => [
        'Post',
        'Comment',
        'App\\Models\\*',
    ],
];
```

## Add Global Scopes (Row Level Security)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Post extends Model
{
    protected static function booted()
    {
        // User isolation
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });

        // Multi-tenancy
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });
    }
}
```

## Security Strategies

### Whitelist (Recommended)

```php
'security' => ['strategy' => 'whitelist'],
'allowed_models' => ['Post', 'Comment'],
```

### Trait-based

```php
'security' => ['strategy' => 'trait'],
```

```php
use RemoteEloquent\Server\Traits\RemoteQueryable;

class Post extends Model
{
    use RemoteQueryable;
}
```

## API Endpoints

- `POST /api/remote-eloquent/execute` - Execute query
- `GET /api/remote-eloquent/health` - Health check

## License

MIT
