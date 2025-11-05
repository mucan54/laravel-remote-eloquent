# Laravel Remote Eloquent - Client Package

Client package for executing Eloquent queries on a remote Laravel backend.

## Installation

```bash
composer require mucan54/laravel-remote-eloquent-client
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=remote-eloquent-config
```

Configure in `.env`:

```env
REMOTE_ELOQUENT_API_URL=https://api.yourapp.com
REMOTE_ELOQUENT_TOKEN=your-api-token
```

## Usage

### Create a Remote Model

```php
<?php

namespace App\Models;

use RemoteEloquent\Client\RemoteModel;

class Post extends RemoteModel
{
    protected static string $remoteModel = 'Post';
}
```

### Query Like Normal Eloquent

```php
// Get all
$posts = Post::all();

// Query with conditions
$posts = Post::where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->get();

// Eager load relationships
$posts = Post::with(['user', 'comments'])
    ->latest()
    ->paginate(20);

// Find by ID
$post = Post::find(1);
```

## Authentication

Store the token after login:

```php
// After login
cache()->put('remote_eloquent_token', $token);

// All queries will now use this token
$posts = Post::all();
```

## Error Handling

```php
use RemoteEloquent\Client\Exceptions\RemoteQueryException;

try {
    $posts = Post::where('status', 'published')->get();
} catch (RemoteQueryException $e) {
    logger()->error('Query failed', [
        'error' => $e->getMessage(),
        'status' => $e->getStatusCode(),
    ]);
}
```

## License

MIT
