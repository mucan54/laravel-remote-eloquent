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

### Batch Services (Execute Multiple Service Methods!)

Execute multiple service methods in a single request. **Works in BOTH modes!**

```php
use RemoteEloquent\Client\BatchService;

// Works in both client and server modes!
$paymentService = new PaymentService();
$emailService = new EmailService();
$smsService = new SmsService();

$results = BatchService::run([
    'charge' => [$paymentService, 'processPayment', [1000, $token]],
    'email' => [$emailService, 'sendReceipt', [$userId, $orderId]],
    'sms' => [$smsService, 'sendConfirmation', [$phone]],
]);

// Access results
$chargeId = $results['charge'];  // "ch_1234567890"
$emailSent = $results['email'];  // true
$smsSent = $results['sms'];      // true
```

**Real-World Example:**
```php
// Complete checkout flow - 1 request instead of 3!
$paymentService = new PaymentService();
$emailService = new EmailService();
$inventoryService = new InventoryService();

$results = BatchService::run([
    'payment' => [$paymentService, 'processPayment', [$amount, $token]],
    'email' => [$emailService, 'sendInvoice', [$userId, $orderId]],
    'inventory' => [$inventoryService, 'decrementStock', [$productId, $quantity]],
]);

if (!isset($results['payment']['error'])) {
    // All succeeded!
    $chargeId = $results['payment'];
}
```

**Benefits:**
- ✅ Multiple service calls = 1 HTTP request
- ✅ Works in both client and server modes
- ✅ Automatic error handling per service
- ✅ Perfect for complex workflows (checkout, registration, etc.)

### Conditional Batch Execution (Advanced Workflows!)

Execute services with **dependency management** and **conditional logic**. Perfect for complex workflows where later steps depend on earlier results!

**Works in BOTH modes** - Same code everywhere!

#### Simple Example: Payment → Email → SMS

```php
use RemoteEloquent\Client\BatchService;

$paymentService = new PaymentService();
$emailService = new EmailService();
$smsService = new SmsService();

$results = BatchService::run([
    // Step 1: Process payment first
    'payment' => [$paymentService, 'charge', [1000, $token]],

    // Step 2: Send email ONLY if payment succeeds
    'email' => [
        'service' => $emailService,
        'method' => 'sendReceipt',
        'args' => fn($results) => [$userId, $results['payment']['orderId']],
        'depends_on' => ['payment'],
        'on_failure' => 'skip', // skip email if payment fails
    ],

    // Step 3: Send SMS ONLY if payment succeeds
    'sms' => [
        'service' => $smsService,
        'method' => 'sendConfirmation',
        'args' => fn($results) => [$phone, $results['payment']['orderId']],
        'depends_on' => ['payment'],
        'on_failure' => 'skip',
    ],
]);

// Check results
if (isset($results['payment']['orderId'])) {
    echo "Payment successful! Order ID: {$results['payment']['orderId']}";
    echo "Email sent: " . ($results['email'] ? 'Yes' : 'Skipped');
    echo "SMS sent: " . ($results['sms'] ? 'Yes' : 'Skipped');
} else {
    echo "Payment failed: {$results['payment']['error']}";
}
```

#### Configuration Options

**Extended Format:**
```php
[
    'key' => [
        'service' => $serviceInstance,              // Required: Service instance
        'method' => 'methodName',                    // Required: Method to call
        'args' => [...] or fn($results) => [...],   // Optional: Arguments or closure
        'depends_on' => ['step1', 'step2'],         // Optional: Dependencies (executes after)
        'on_failure' => 'stop',                     // Optional: stop|skip|continue
    ]
]
```

**Simple Format (backward compatible):**
```php
[
    'key' => [$service, 'method', $args]
]
```

#### Failure Strategies

**1. `stop` (default)** - Stop entire batch if this fails:
```php
'payment' => [
    'service' => $paymentService,
    'method' => 'charge',
    'args' => [1000, $token],
    'on_failure' => 'stop', // Stop everything if payment fails
]
```

**2. `skip`** - Skip dependent steps but continue others:
```php
'email' => [
    'service' => $emailService,
    'method' => 'send',
    'args' => [$userId],
    'depends_on' => ['payment'],
    'on_failure' => 'skip', // Skip if payment fails, but continue other steps
]
```

**3. `continue`** - Try anyway even if dependencies fail:
```php
'analytics' => [
    'service' => $analyticsService,
    'method' => 'track',
    'args' => ['checkout_attempted'],
    'on_failure' => 'continue', // Always try, even if previous steps fail
]
```

#### Real-World Example: Complete Checkout Flow

```php
$paymentService = new PaymentService();
$emailService = new EmailService();
$inventoryService = new InventoryService();
$analyticsService = new AnalyticsService();

$results = BatchService::run([
    // Step 1: Check inventory
    'inventory_check' => [
        'service' => $inventoryService,
        'method' => 'checkAvailability',
        'args' => [$productId, $quantity],
        'on_failure' => 'stop', // Stop if out of stock
    ],

    // Step 2: Process payment (depends on inventory)
    'payment' => [
        'service' => $paymentService,
        'method' => 'charge',
        'args' => fn($r) => [$r['inventory_check']['price'], $token],
        'depends_on' => ['inventory_check'],
        'on_failure' => 'stop',
    ],

    // Step 3: Decrement inventory (depends on payment)
    'inventory_update' => [
        'service' => $inventoryService,
        'method' => 'decrementStock',
        'args' => fn($r) => [$productId, $quantity, $r['payment']['orderId']],
        'depends_on' => ['payment'],
        'on_failure' => 'stop',
    ],

    // Step 4: Send receipt (depends on payment)
    'email' => [
        'service' => $emailService,
        'method' => 'sendReceipt',
        'args' => fn($r) => [$userId, $r['payment']['orderId']],
        'depends_on' => ['payment'],
        'on_failure' => 'skip', // Skip email if payment fails
    ],

    // Step 5: Track analytics (always run)
    'analytics' => [
        'service' => $analyticsService,
        'method' => 'trackPurchase',
        'args' => fn($r) => [$userId, $r['payment']['orderId'] ?? null],
        'on_failure' => 'continue', // Track even if payment fails
    ],
]);
```

#### Using Closure Arguments

Pass data between steps using closures. The `$results` array contains all previous results:

```php
$results = BatchService::run([
    'user' => [$userService, 'create', ['john@example.com']],

    'profile' => [
        'service' => $profileService,
        'method' => 'create',
        'args' => fn($r) => [
            'user_id' => $r['user']['id'],
            'name' => 'John Doe',
        ],
        'depends_on' => ['user'],
    ],

    'welcome_email' => [
        'service' => $emailService,
        'method' => 'sendWelcome',
        'args' => fn($r) => [
            $r['user']['email'],
            $r['user']['id'],
        ],
        'depends_on' => ['user'],
    ],
]);
```

**Important:** Closures work in **server mode only**. In client mode without closures, dependencies are still respected but you must provide static arguments.

#### Multiple Dependencies

A step can depend on multiple previous steps:

```php
$results = BatchService::run([
    'user' => [$userService, 'create', ['john@example.com']],
    'payment' => [$paymentService, 'charge', [1000, $token]],

    'order' => [
        'service' => $orderService,
        'method' => 'create',
        'args' => fn($r) => [
            'user_id' => $r['user']['id'],
            'payment_id' => $r['payment']['id'],
        ],
        'depends_on' => ['user', 'payment'], // Both must succeed
        'on_failure' => 'stop',
    ],
]);
```

#### Error Handling

```php
$results = BatchService::run([...]);

// Check individual results
if (isset($results['payment']['error'])) {
    echo "Payment failed: {$results['payment']['error']}";
}

if (isset($results['email']['skipped'])) {
    echo "Email was skipped: {$results['email']['reason']}";
}

// Check success
if (!isset($results['payment']['error'])) {
    echo "Order created successfully!";
}
```

#### Validation

The system automatically validates:
- ✅ **Circular dependencies** - Detects A → B → A loops
- ✅ **Missing dependencies** - Ensures all dependencies exist
- ✅ **Topological ordering** - Executes in correct order
- ✅ **Failure propagation** - Handles failures according to strategy

**Example Error:**
```php
// This will throw an exception:
BatchService::run([
    'a' => [
        'service' => $service,
        'method' => 'methodA',
        'depends_on' => ['b'], // b depends on a (circular!)
    ],
    'b' => [
        'service' => $service,
        'method' => 'methodB',
        'depends_on' => ['a'],
    ],
]);
// Exception: "Circular dependency detected: 'a' <-> 'b'"
```

**Benefits:**
- ✅ Complex workflows in one request
- ✅ Automatic dependency resolution
- ✅ Pass data between steps via closures
- ✅ Flexible failure handling
- ✅ Works in both client and server modes
- ✅ Validation prevents circular dependencies
- ✅ Perfect for checkout, registration, multi-step processes

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
