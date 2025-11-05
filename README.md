# Laravel Remote Eloquent

Execute Eloquent queries on a remote Laravel backend with security, Global Scopes support, and Eloquent-like syntax.

Perfect for **NativePHP mobile applications** that need to query data from a remote PostgreSQL/MySQL database.

## 🚀 Features

- ✅ **Eloquent-like API** - Use familiar Eloquent syntax
- ✅ **Global Scopes** - Automatic Row Level Security (RLS)
- ✅ **Multi-tenancy** - Built-in tenant isolation
- ✅ **Relationships** - Eager loading, nested queries
- ✅ **Security** - Multiple validation layers, no code execution
- ✅ **Pagination** - Full pagination support
- ✅ **Aggregates** - count, sum, avg, max, min
- ✅ **Type-safe** - No SQL injection risks

## 📦 Packages

This project consists of two packages:

1. **Client Package** (`mucan54/laravel-remote-eloquent-client`) - For NativePHP mobile apps
2. **Server Package** (`mucan54/laravel-remote-eloquent-server`) - For Laravel backend API

---

## 📱 Client Package Installation

Install in your **NativePHP mobile application**:

```bash
composer require mucan54/laravel-remote-eloquent-client
```

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=remote-eloquent-config
```

Configure your API URL in `.env`:

```env
REMOTE_ELOQUENT_API_URL=https://api.yourapp.com
REMOTE_ELOQUENT_TOKEN=your-api-token
```

### Usage

Create a remote model:

```php
<?php

namespace App\Models;

use RemoteEloquent\Client\RemoteModel;

class Post extends RemoteModel
{
    protected static string $remoteModel = 'Post';
}
```

Use it like normal Eloquent:

```php
// Get all posts
$posts = Post::all();

// Query with conditions
$posts = Post::where('status', 'published')
    ->where('user_id', 123)
    ->orderBy('created_at', 'desc')
    ->get();

// Eager load relationships
$posts = Post::with(['user', 'comments'])
    ->latest()
    ->paginate(20);

// Nested queries with closures
$posts = Post::whereHas('comments', function($query) {
    $query->where('approved', true);
})->get();

// Aggregates
$count = Post::where('status', 'published')->count();
$avgRating = Post::avg('rating');

// Find by ID
$post = Post::find(1);
$post = Post::findOrFail(1);
```

---

## ☁️ Server Package Installation

Install in your **Laravel backend API**:

```bash
composer require mucan54/laravel-remote-eloquent-server
```

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=remote-eloquent-config
```

Configure in `config/remote-eloquent.php`:

```php
<?php

return [
    'enabled' => true,

    'security' => [
        'strategy' => 'whitelist', // whitelist, blacklist, or trait
        'require_auth' => true,
    ],

    'allowed_models' => [
        'Post',
        'Comment',
        'Product',
        'App\\Models\\*', // All models in App\Models
    ],

    'blocked_models' => [
        'User',
        'Password',
    ],
];
```

### Add Global Scopes (Row Level Security)

This is the **magic** that makes Row Level Security work automatically:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Post extends Model
{
    protected static function booted()
    {
        // Only show posts belonging to the authenticated user
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

Now when the mobile app calls `Post::all()`, the SQL executed will be:

```sql
SELECT * FROM posts
WHERE user_id = 123          -- Automatically added!
  AND tenant_id = 5          -- Automatically added!
```

### Authentication

The package uses **Laravel Sanctum** for authentication:

```php
// In your AuthController (backend)
public function login(Request $request)
{
    // Validate credentials...

    $token = $user->createToken('mobile-app')->plainTextToken;

    return response()->json(['token' => $token]);
}
```

In the mobile app, store the token:

```php
// After login
cache()->put('remote_eloquent_token', $token);

// Now all queries will use this token
$posts = Post::all(); // Authenticated as this user
```

---

## 🔒 Security Architecture

The package uses multiple security layers:

1. **Authentication** - JWT Token (Laravel Sanctum)
2. **Rate Limiting** - 100 requests/minute (configurable)
3. **Model Whitelist** - Only allowed models can be queried
4. **Method Whitelist** - Only safe methods are allowed
5. **Parameter Validation** - All parameters are validated
6. **Global Scopes** - Automatic Row Level Security

### Security Strategies

#### 1. Whitelist (Recommended)

Only explicitly allowed models can be queried:

```php
'security' => [
    'strategy' => 'whitelist',
],

'allowed_models' => [
    'Post',
    'Comment',
    'App\\Models\\*',
],
```

#### 2. Blacklist

All models allowed except blocked ones:

```php
'security' => [
    'strategy' => 'blacklist',
],

'blocked_models' => [
    'User',
    'Password',
],
```

#### 3. Trait-based

Only models with the `RemoteQueryable` trait:

```php
'security' => [
    'strategy' => 'trait',
],
```

```php
use RemoteEloquent\Server\Traits\RemoteQueryable;

class Post extends Model
{
    use RemoteQueryable; // ✅ Can be queried
}

class User extends Model
{
    // ❌ Cannot be queried (no trait)
}
```

---

## 📊 Supported Methods

### Chain Methods (Non-terminal)

- **Where**: `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereBetween`, `whereNull`, `whereNotNull`, `whereDate`, `whereColumn`
- **Relationships**: `with`, `withCount`, `has`, `whereHas`, `doesntHave`
- **Ordering**: `orderBy`, `orderByDesc`, `latest`, `oldest`, `inRandomOrder`
- **Limiting**: `limit`, `take`, `skip`, `offset`
- **Selecting**: `select`, `addSelect`, `distinct`
- **Grouping**: `groupBy`, `having`
- **Joins**: `join`, `leftJoin`, `rightJoin`

### Terminal Methods (Execute query)

- **Reading**: `get`, `first`, `find`, `findOrFail`, `sole`, `value`, `pluck`
- **Aggregates**: `count`, `sum`, `avg`, `max`, `min`
- **Existence**: `exists`, `doesntExist`
- **Pagination**: `paginate`, `simplePaginate`

### Forbidden Methods

For security, these methods are **NOT** allowed:

- `raw`, `rawQuery`, `selectRaw`, `whereRaw` (SQL injection risk)
- `truncate`, `drop` (Destructive)
- `create`, `insert`, `update`, `delete` (Disabled by default)

---

## 🎯 Complete Example

### Mobile App (Client)

```php
<?php

namespace App\Livewire;

use App\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;

class PostList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'published';

    public function render()
    {
        $posts = Post::query()
            ->with(['user', 'comments'])
            ->when($this->search, function($query) {
                $query->where('title', 'like', "%{$this->search}%");
            })
            ->where('status', $this->status)
            ->latest()
            ->paginate(20);

        return view('livewire.post-list', [
            'posts' => $posts,
        ]);
    }
}
```

### Backend API (Server)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['user_id', 'tenant_id', 'title', 'content', 'status'];

    /**
     * Global Scopes - Automatic Row Level Security
     */
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

        // Only published posts
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('status', 'published');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

---

## 🔧 Configuration Options

### Client Configuration

```php
// config/remote-eloquent.php (client)

return [
    'api_url' => env('REMOTE_ELOQUENT_API_URL'),

    'auth' => [
        'driver' => 'cache', // cache, session, or config
        'cache_key' => 'remote_eloquent_token',
    ],

    'request' => [
        'timeout' => 30,
        'retry' => [
            'enabled' => true,
            'times' => 3,
        ],
    ],
];
```

### Server Configuration

```php
// config/remote-eloquent.php (server)

return [
    'enabled' => true,

    'security' => [
        'strategy' => 'whitelist',
        'require_auth' => true,
        'rate_limiting' => [
            'enabled' => true,
            'limit' => 100, // per minute
        ],
    ],

    'allowed_models' => ['Post', 'Comment'],
    'blocked_models' => ['User', 'Password'],

    'limits' => [
        'max_limit' => 1000,
        'max_execution_time' => 30,
    ],

    'logging' => [
        'enabled' => true,
        'log_slow_queries' => true,
        'slow_query_threshold' => 1000, // ms
    ],
];
```

---

## 🐛 Error Handling

```php
use RemoteEloquent\Client\Exceptions\RemoteQueryException;

try {
    $posts = Post::where('status', 'published')->get();
} catch (RemoteQueryException $e) {
    // Handle error
    logger()->error('Remote query failed', [
        'error' => $e->getMessage(),
        'status_code' => $e->getStatusCode(),
        'context' => $e->getContext(),
    ]);

    // Fallback
    $posts = collect([]);
}
```

---

## 📈 Performance Tips

1. **Use eager loading** to avoid N+1 problems:
   ```php
   // ❌ BAD - N+1 problem
   $posts = Post::all();
   foreach ($posts as $post) {
       echo $post->user->name; // Extra query for each post!
   }

   // ✅ GOOD - Eager loading
   $posts = Post::with('user')->get();
   foreach ($posts as $post) {
       echo $post->user->name; // No extra queries!
   }
   ```

2. **Use pagination** instead of `get()`:
   ```php
   // ✅ Pagination
   $posts = Post::paginate(20);
   ```

3. **Select only needed columns**:
   ```php
   $posts = Post::select(['id', 'title', 'created_at'])->get();
   ```

4. **Use caching** (if enabled):
   ```php
   'cache' => [
       'enabled' => true,
       'ttl' => 60, // seconds
   ],
   ```

---

## 🧪 Testing

### Test Remote Query

```php
// Test health endpoint
GET https://api.yourapp.com/api/remote-eloquent/health

// Execute query
POST https://api.yourapp.com/api/remote-eloquent/execute
Authorization: Bearer {token}
Content-Type: application/json

{
    "model": "Post",
    "chain": [
        {
            "method": "where",
            "parameters": ["status", "published"]
        },
        {
            "method": "orderBy",
            "parameters": ["created_at", "desc"]
        }
    ],
    "method": "get",
    "parameters": []
}
```

---

## 📝 License

MIT

---

## 👤 Author

**mucan54**

---

## 🙏 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## ⚠️ Important Notes

1. **Global Scopes are CRITICAL** - Always add Global Scopes to your models for Row Level Security
2. **Authentication is REQUIRED** - Never disable authentication in production
3. **Test thoroughly** - Test your Global Scopes to ensure data isolation
4. **Monitor performance** - Enable slow query logging
5. **Use HTTPS** - Always use HTTPS in production

---

## 📚 How It Works

```
┌─────────────────────────────────────┐
│   📱 NativePHP Mobile App           │
│   Post::where('status', 1)->get()   │
└───────────────┬─────────────────────┘
                │
                │ HTTPS + JWT
                │ AST (JSON)
                │
┌───────────────▼─────────────────────┐
│   ☁️  Laravel Backend API           │
│   1. Validate Model (whitelist)     │
│   2. Validate Methods (whitelist)   │
│   3. Reconstruct Query              │
│   4. Global Scopes Apply! ✨        │
│   5. Execute on Database            │
└───────────────┬─────────────────────┘
                │
┌───────────────▼─────────────────────┐
│   🗄️  PostgreSQL / MySQL            │
│   SELECT * FROM posts               │
│   WHERE user_id = 123 (Auto! ✅)    │
│     AND status = 1                  │
└─────────────────────────────────────┘
```

---

## 🎉 Happy Coding!

If you have any questions or issues, please open an issue on GitHub.
