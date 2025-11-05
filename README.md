# Laravel Remote Eloquent

**One package. Two modes. Same codebase.**

Execute Eloquent queries on a remote Laravel backend with automatic Row Level Security. Perfect for NativePHP mobile apps.

## Installation

```bash
composer require mucan54/laravel-remote-eloquent
```

Publish configuration:

```bash
php artisan vendor:publish --tag=remote-eloquent-config
```

## Configuration

### Client Mode (NativePHP Mobile App)

`.env`:
```env
REMOTE_ELOQUENT_MODE=client
REMOTE_ELOQUENT_API_URL=https://api.yourapp.com
```

After login, store the token:
```php
cache()->put('remote_eloquent_token', $token);
```

### Server Mode (Backend API)

`.env`:
```env
REMOTE_ELOQUENT_MODE=server
REMOTE_ELOQUENT_REQUIRE_AUTH=true
```

`config/remote-eloquent.php`:
```php
'allowed_models' => [
    'Post',
    'Comment',
    'Product',
],
```

## Usage

### Same Model, Both Environments

```php
<?php

namespace App\Models;

use RemoteEloquent\RemoteModel;
use Illuminate\Database\Eloquent\Builder;

class Post extends RemoteModel
{
    protected $fillable = ['user_id', 'title', 'content', 'status'];

    /**
     * Global Scopes (Server Mode Only)
     * Automatic Row Level Security!
     */
    protected static function booted()
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Query Anywhere

```php
// Works in both client and server modes!
$posts = Post::where('status', 'published')
    ->with('user')
    ->latest()
    ->paginate(20);

// Client mode: Sends API request to backend
// Server mode: Executes on local database with Global Scopes
```

## How It Works

```
┌─────────────────────────────────┐
│  NativePHP Mobile App           │
│  REMOTE_ELOQUENT_MODE=client    │
│                                 │
│  Post::where('status', 1)->get()│
│         ↓                       │
│  Sends JSON AST to API          │
└────────────┬────────────────────┘
             │ HTTPS + Token
             │
┌────────────▼────────────────────┐
│  Laravel Backend API            │
│  REMOTE_ELOQUENT_MODE=server    │
│                                 │
│  1. Validate model whitelist    │
│  2. Validate method whitelist   │
│  3. Execute query locally       │
│  4. Global Scopes apply! ✅     │
└────────────┬────────────────────┘
             │
┌────────────▼────────────────────┐
│  PostgreSQL / MySQL             │
│  SELECT * FROM posts            │
│  WHERE user_id = 123 (Auto! ✅) │
│    AND status = 1               │
└─────────────────────────────────┘
```

## Key Features

### ✅ One Package, Two Modes
- **Client mode**: Sends queries to API
- **Server mode**: Executes locally
- Same models work everywhere

### ✅ Global Scopes = Row Level Security
```php
// Backend model
static::addGlobalScope('user', function (Builder $builder) {
    if (auth()->check()) {
        $builder->where('user_id', auth()->id());
    }
});

// Mobile app just calls
Post::all();

// SQL executed: WHERE user_id = 123 (Automatic!)
```

### ✅ Full Eloquent Support
- Relationships: `with()`, `has()`, `whereHas()`
- Queries: `where()`, `orderBy()`, `groupBy()`
- Aggregates: `count()`, `sum()`, `avg()`
- Pagination: `paginate()`, `simplePaginate()`

### ✅ Secure by Default
- Authentication required (Laravel Sanctum)
- Model whitelist
- Method whitelist
- No SQL injection
- No code execution

## Examples

### Complex Queries

```php
$posts = Post::with(['user', 'comments' => function($query) {
        $query->where('approved', true)
              ->orderBy('created_at', 'desc')
              ->limit(5);
    }])
    ->where('status', 'published')
    ->whereHas('comments', function($query) {
        $query->where('rating', '>', 3);
    })
    ->latest()
    ->paginate(20);
```

### Multi-Tenancy

```php
// Backend model
protected static function booted()
{
    // User isolation
    static::addGlobalScope('user', function (Builder $builder) {
        if (auth()->check()) {
            $builder->where('user_id', auth()->id());
        }
    });

    // Tenant isolation
    static::addGlobalScope('tenant', function (Builder $builder) {
        if (auth()->check() && auth()->user()->tenant_id) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    });
}
```

### Livewire Component

```php
use App\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;

class PostList extends Component
{
    use WithPagination;

    public string $search = '';

    public function render()
    {
        $posts = Post::query()
            ->with('user')
            ->when($this->search, fn($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(20);

        return view('livewire.post-list', ['posts' => $posts]);
    }
}
```

## Configuration Reference

```php
// config/remote-eloquent.php

return [
    // 'client' or 'server'
    'mode' => env('REMOTE_ELOQUENT_MODE', 'server'),

    // Client: API URL
    'api_url' => env('REMOTE_ELOQUENT_API_URL'),

    // Server: Require auth
    'require_auth' => env('REMOTE_ELOQUENT_REQUIRE_AUTH', true),

    // Server: Model whitelist
    'allowed_models' => [
        'Post',
        'Comment',
    ],

    // Allowed methods
    'allowed_methods' => [
        'chain' => ['where', 'with', 'orderBy', 'limit', ...],
        'terminal' => ['get', 'first', 'find', 'count', 'paginate', ...],
    ],
];
```

## Environment Variables

### Client (NativePHP)
```env
REMOTE_ELOQUENT_MODE=client
REMOTE_ELOQUENT_API_URL=https://api.yourapp.com
```

### Server (Backend)
```env
REMOTE_ELOQUENT_MODE=server
REMOTE_ELOQUENT_REQUIRE_AUTH=true
```

## Security Checklist

- [x] Use `REMOTE_ELOQUENT_MODE=server` on backend
- [x] Use `REMOTE_ELOQUENT_MODE=client` on mobile
- [x] Configure `allowed_models` whitelist
- [x] Add Global Scopes to all models
- [x] Enable authentication (`REMOTE_ELOQUENT_REQUIRE_AUTH=true`)
- [x] Use HTTPS in production
- [x] Test your Global Scopes

## API Endpoints

When in server mode, these endpoints are automatically registered:

- `POST /api/remote-eloquent/execute` - Execute query
- `GET /api/remote-eloquent/health` - Health check

## Testing

```php
// Test health
GET https://api.yourapp.com/api/remote-eloquent/health

// Test query
POST https://api.yourapp.com/api/remote-eloquent/execute
Authorization: Bearer {token}

{
    "model": "Post",
    "chain": [
        {"method": "where", "parameters": ["status", "published"]},
        {"method": "orderBy", "parameters": ["created_at", "desc"]}
    ],
    "method": "get",
    "parameters": []
}
```

## License

MIT

## Author

**mucan54**

---

## Why This Package?

**Problem**: NativePHP mobile apps need to query remote databases, but traditional APIs are messy:

```php
// ❌ Traditional way
$response = Http::post('/api/posts', ['filters' => ...]);
$posts = $response->json('data');
```

**Solution**: Use Eloquent syntax everywhere:

```php
// ✅ With this package
$posts = Post::where('status', 'published')->get();
```

**Same code**. Client or server. **Less is more.**
