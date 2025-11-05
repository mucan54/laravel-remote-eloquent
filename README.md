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

**Install Laravel Sanctum** (recommended for mobile apps):
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\ServiceProvider"
php artisan migrate
```

`.env`:
```env
REMOTE_ELOQUENT_MODE=server
REMOTE_ELOQUENT_AUTH_MIDDLEWARE=auth:sanctum
```

`config/remote-eloquent.php`:
```php
// Authentication (defaults to Sanctum)
'auth_middleware' => env('REMOTE_ELOQUENT_AUTH_MIDDLEWARE', 'auth:sanctum'),

// Model whitelist
'allowed_models' => [
    'Post',
    'Comment',
    'Product',
],
```

**Alternative authentication:**
```php
// Laravel Passport
'auth_middleware' => 'auth:api',

// JWT Auth
'auth_middleware' => 'jwt.auth',

// Multiple middleware
'auth_middleware' => ['auth:sanctum', 'verified'],

// Disable authentication (NOT recommended)
'auth_middleware' => null,
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

### Batch Queries (Performance!)

Execute multiple queries in a single request. **Works in BOTH modes!**

```php
use RemoteEloquent\Client\BatchQuery;

// Client mode: 1 HTTP request instead of 4!
// Server mode: Executes locally, same code!
$results = BatchQuery::run([
    'posts' => Post::where('status', 'published')->limit(10),
    'recentComments' => Comment::latest()->limit(5),
    'postCount' => Post::where('status', 'published')->count(),
    'userData' => User::with('profile')->find(auth()->id()),
]);

// Access results (works everywhere!)
$posts = $results['posts'];              // Collection
$recentComments = $results['recentComments'];  // Collection
$postCount = $results['postCount'];      // int
$userData = $results['userData'];        // object

// Advanced: Custom method/parameters
$results = BatchQuery::run([
    'published' => [
        'query' => Post::where('status', 'published'),
        'method' => 'paginate',
        'parameters' => [20]
    ],
    'drafts' => [
        'query' => Post::where('status', 'draft'),
        'method' => 'count',
        'parameters' => []
    ],
]);
```

**Benefits:**
- ✅ Reduce HTTP requests (10 queries = 1 request instead of 10!)
- ✅ Better mobile app performance
- ✅ Lower latency
- ✅ Automatic error handling per query
- ✅ **Works in both modes - same code everywhere!**

### Remote Services (Server-Side Logic!)

Execute service methods remotely when they need server-side credentials or resources.

**Use Cases:**
- Payment processing (Stripe keys only on server)
- Email sending (SMTP credentials only on server)
- External API calls (API keys only on server)
- AWS/Cloud operations (credentials only on server)

**Usage:**

```php
<?php

namespace App\Services;

use RemoteEloquent\Client\RemoteService;

class PaymentService
{
    use RemoteService;

    /**
     * Methods in this array will execute on SERVER
     * (has access to STRIPE_SECRET_KEY in server .env)
     */
    protected array $remoteMethods = [
        'processPayment',
        'refundPayment',
        'createCustomer',
    ];

    /**
     * This runs on SERVER (has Stripe secret key)
     */
    public function processPayment(int $amount, string $token)
    {
        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $charge = \Stripe\Charge::create([
            'amount' => $amount,
            'currency' => 'usd',
            'source' => $token,
        ]);

        return $charge->id;
    }

    /**
     * This runs LOCALLY (not in $remoteMethods)
     */
    public function calculateFee(int $amount)
    {
        return $amount * 0.029 + 30; // Local calculation
    }
}
```

**In Mobile App:**
```php
$paymentService = new PaymentService();

// Executes on SERVER (secure!)
$chargeId = $paymentService->processPayment(1000, $token);

// Executes LOCALLY
$fee = $paymentService->calculateFee(1000);
```

**Server Configuration:**
```php
// config/remote-eloquent.php
'allowed_services' => [
    'App\Services\PaymentService',
    'App\Services\EmailService',
    'App\Services\*', // All services in App\Services
],
```

**Real-World Example (Email Service):**
```php
class EmailService
{
    use RemoteService;

    protected array $remoteMethods = ['sendWelcomeEmail', 'sendInvoice'];

    public function sendWelcomeEmail(int $userId)
    {
        $user = User::find($userId);

        // Server has MAIL_* credentials
        Mail::to($user->email)->send(new WelcomeEmail($user));

        return true;
    }
}

// Mobile app
$emailService = new EmailService();
$emailService->sendWelcomeEmail(auth()->id()); // Executes on server
```

**Benefits:**
- ✅ Keep secrets on server only
- ✅ Same code works in both environments
- ✅ Automatic serialization/deserialization
- ✅ Type-safe method calls

### Batch Services with Pipeline Pattern 🚀

Execute multiple service methods with an elegant fluent interface. **Works in BOTH modes!**

#### Quick Example

```php
use RemoteEloquent\Client\BatchService;

$paymentService = new PaymentService();
$emailService = new EmailService();
$smsService = new SmsService();

// Fluent pipeline - clean and readable!
$results = BatchService::pipeline()
    ->step('payment', [$paymentService, 'charge', [1000, $token]])
        ->stopOnFailure()
    ->step('email', [$emailService, 'send', fn($prev) => [$prev['payment']['orderId']]])
        ->skipOnFailure()
    ->step('sms', [$smsService, 'send', fn($prev) => [$prev['payment']['orderId']]])
        ->skipOnFailure()
    ->execute();

// Check results
if (!isset($results['payment']['error'])) {
    echo "Payment successful! Order: {$results['payment']['orderId']}";
}
```

**What just happened?**
1. ✅ Payment processes first
2. ✅ If payment succeeds, email sent with order ID from payment result
3. ✅ If payment succeeds, SMS sent with order ID from payment result
4. ✅ If payment fails, email and SMS are automatically skipped
5. ✅ **All in ONE HTTP request!**

#### Pipeline API

**Adding Steps:**
```php
->step('key', [$service, 'method', $args])
// or
->step('key', $service, 'method', $args)
```

**Failure Strategies:**
- `->stopOnFailure()` - Stop entire pipeline if this step fails (default)
- `->skipOnFailure()` - Skip dependent steps if this fails
- `->continueOnFailure()` - Continue even if this fails

**Explicit Dependencies:**
```php
->step('order', [$orderService, 'create', fn($p) => [...]])
    ->dependsOn('user', 'payment') // Explicit dependencies
```

**Closure Arguments:**
```php
// Access previous results via closure
->step('email', [$emailService, 'send', fn($prev) => [
    $userId,
    $prev['payment']['orderId'],
    $prev['inventory']['productName']
]])
```

#### Real-World Example: Complete Checkout

```php
$results = BatchService::pipeline()
    // Step 1: Check inventory
    ->step('inventory', [$inventoryService, 'check', [$productId, $quantity]])
        ->stopOnFailure()

    // Step 2: Process payment (uses inventory price)
    ->step('payment', $paymentService, 'charge', fn($p) => [
        $p['inventory']['price'],
        $token
    ])
        ->stopOnFailure()

    // Step 3: Update inventory (uses payment order ID)
    ->step('inventory_update', $inventoryService, 'decrement', fn($p) => [
        $productId,
        $quantity,
        $p['payment']['orderId']
    ])
        ->stopOnFailure()

    // Step 4: Send receipt email
    ->step('email', $emailService, 'sendReceipt', fn($p) => [
        $userId,
        $p['payment']['orderId']
    ])
        ->skipOnFailure() // Don't fail checkout if email fails

    // Step 5: Send SMS confirmation
    ->step('sms', $smsService, 'sendConfirmation', fn($p) => [
        $phone,
        $p['payment']['orderId']
    ])
        ->skipOnFailure()

    // Step 6: Track analytics (always run)
    ->step('analytics', $analyticsService, 'track', fn($p) => [
        'checkout_completed',
        $p['payment']['orderId'] ?? null
    ])
        ->continueOnFailure()

    ->execute();

// Handle results
if (!isset($results['payment']['error'])) {
    echo "Order {$results['payment']['orderId']} created!";
    echo $results['email'] ? "Receipt sent!" : "Email failed";
    echo $results['sms'] ? "SMS sent!" : "SMS failed";
} else {
    echo "Checkout failed: {$results['payment']['error']}";
}
```

#### User Registration Workflow

```php
$results = BatchService::pipeline()
    // Create user account
    ->step('user', [$userService, 'create', [$email, $password]])
        ->stopOnFailure()

    // Create user profile
    ->step('profile', $profileService, 'create', fn($p) => [
        $p['user']['id'],
        $name,
        $avatar
    ])
        ->stopOnFailure()

    // Send welcome email
    ->step('welcome_email', $emailService, 'sendWelcome', fn($p) => [
        $p['user']['email'],
        $p['user']['name']
    ])
        ->skipOnFailure()

    // Create default settings
    ->step('settings', $settingsService, 'createDefaults', fn($p) => [
        $p['user']['id']
    ])
        ->skipOnFailure()

    // Track registration
    ->step('analytics', $analyticsService, 'trackRegistration', fn($p) => [
        $p['user']['id'],
        'source' => 'mobile_app'
    ])
        ->continueOnFailure()

    ->execute();
```

#### Alternative: Array-Based API (Advanced)

For advanced use cases, you can use the array-based API with explicit configuration:

```php
$results = BatchService::run([
    'payment' => [
        'service' => $paymentService,
        'method' => 'charge',
        'args' => [1000, $token],
        'on_failure' => 'stop',
    ],
    'email' => [
        'service' => $emailService,
        'method' => 'send',
        'args' => fn($r) => [$r['payment']['orderId']],
        'depends_on' => ['payment'],
        'on_failure' => 'skip',
    ],
]);
```

Or simple format:
```php
$results = BatchService::run([
    'payment' => [$paymentService, 'charge', [1000, $token]],
    'email' => [$emailService, 'send', [$userId, $orderId]],
]);
```

#### Error Handling

```php
$results = BatchService::pipeline()
    ->step('payment', [$paymentService, 'charge', [1000]])
    ->step('email', [$emailService, 'send', fn($p) => [$p['payment']]])
    ->execute();

// Check for errors
if (isset($results['payment']['error'])) {
    echo "Error: {$results['payment']['error']}";
}

// Check if skipped
if (isset($results['email']['skipped'])) {
    echo "Email skipped: {$results['email']['reason']}";
}

// Check success
if (!isset($results['payment']['error']) && !isset($results['email']['error'])) {
    echo "All steps completed successfully!";
}
```

#### Key Features

✅ **Fluent Interface** - Clean, readable method chaining
✅ **Implicit Dependencies** - Steps execute in order, later steps access earlier results
✅ **Explicit Dependencies** - Use `->dependsOn()` when needed
✅ **Closure Arguments** - Pass data between steps with `fn($prev) => [...]`
✅ **Failure Strategies** - stopOnFailure, skipOnFailure, continueOnFailure
✅ **Works in BOTH modes** - Client mode: 1 HTTP request, Server mode: local execution
✅ **Automatic Validation** - Detects circular dependencies
✅ **Type Safe** - Full IDE autocomplete support

**Important:** Closures work in **server mode only**. In client mode, use static arguments or pre-computed values

## Configuration Reference

```php
// config/remote-eloquent.php

return [
    // 'client' or 'server'
    'mode' => env('REMOTE_ELOQUENT_MODE', 'server'),

    // Client: API URL
    'api_url' => env('REMOTE_ELOQUENT_API_URL'),

    // Server: Authentication middleware (Sanctum by default)
    'auth_middleware' => env('REMOTE_ELOQUENT_AUTH_MIDDLEWARE', 'auth:sanctum'),

    // Server: Model whitelist
    'allowed_models' => [
        'Post',
        'Comment',
    ],

    // Server: Service whitelist
    'allowed_services' => [
        'App\Services\PaymentService',
        'App\Services\*', // All in App\Services
    ],

    // Allowed methods
    'allowed_methods' => [
        'chain' => ['where', 'with', 'orderBy', 'limit', ...],
        'terminal' => ['get', 'first', 'find', 'count', 'paginate', ...],
    ],

    // Batch queries
    'batch' => [
        'enabled' => true,
        'max_queries' => 10,
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
REMOTE_ELOQUENT_AUTH_MIDDLEWARE=auth:sanctum
```

**Optional:**
```env
# Disable authentication (NOT recommended)
REMOTE_ELOQUENT_AUTH_MIDDLEWARE=

# Use Laravel Passport
REMOTE_ELOQUENT_AUTH_MIDDLEWARE=auth:api

# Batch settings
REMOTE_ELOQUENT_BATCH_ENABLED=true
REMOTE_ELOQUENT_BATCH_MAX=10
```

## Security Checklist

- [x] Use `REMOTE_ELOQUENT_MODE=server` on backend
- [x] Use `REMOTE_ELOQUENT_MODE=client` on mobile
- [x] Install and configure Laravel Sanctum
- [x] Configure `allowed_models` whitelist
- [x] Configure `allowed_services` whitelist (if using RemoteService)
- [x] Add Global Scopes to all models
- [x] Keep authentication enabled (`auth_middleware=auth:sanctum`)
- [x] Use HTTPS in production
- [x] Test your Global Scopes
- [x] Only mark necessary methods in `$remoteMethods` array

## API Endpoints

When in server mode, these endpoints are automatically registered:

- `POST /api/remote-eloquent/execute` - Execute single query
- `POST /api/remote-eloquent/batch` - Execute batch queries
- `POST /api/remote-eloquent/service` - Execute remote service method
- `POST /api/remote-eloquent/batch-service` - Execute batch service methods
- `GET /api/remote-eloquent/health` - Health check

## Testing

```php
// Test health
GET https://api.yourapp.com/api/remote-eloquent/health

// Test single query
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

// Test batch queries
POST https://api.yourapp.com/api/remote-eloquent/batch
Authorization: Bearer {token}

{
    "queries": {
        "posts": {
            "model": "Post",
            "chain": [{"method": "where", "parameters": ["status", "published"]}],
            "method": "get",
            "parameters": []
        },
        "postCount": {
            "model": "Post",
            "chain": [{"method": "where", "parameters": ["status", "published"]}],
            "method": "count",
            "parameters": []
        }
    }
}

// Test batch service methods
POST https://api.yourapp.com/api/remote-eloquent/batch-service
Authorization: Bearer {token}

{
    "services": {
        "payment": {
            "service": "App\\Services\\PaymentService",
            "method": "processPayment",
            "arguments": [1000, "tok_visa"]
        },
        "email": {
            "service": "App\\Services\\EmailService",
            "method": "sendReceipt",
            "arguments": [123, 456]
        }
    }
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
