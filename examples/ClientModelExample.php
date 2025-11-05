<?php

namespace App\Models;

use RemoteEloquent\Client\RemoteModel;

/**
 * Example Remote Model for Client (Mobile App)
 *
 * This model will query the remote backend API.
 */
class Post extends RemoteModel
{
    /**
     * The remote model name (on the backend)
     *
     * @var string
     */
    protected static string $remoteModel = 'Post';
}

// Usage Examples:

// 1. Get all posts
$posts = Post::all();

// 2. Query with conditions
$posts = Post::where('status', 'published')
    ->where('user_id', 123)
    ->orderBy('created_at', 'desc')
    ->get();

// 3. Eager load relationships
$posts = Post::with(['user', 'comments'])
    ->latest()
    ->paginate(20);

// 4. Nested queries with closures
$posts = Post::whereHas('comments', function($query) {
    $query->where('approved', true)
          ->where('rating', '>', 3);
})->get();

// 5. Aggregates
$count = Post::where('status', 'published')->count();
$avgRating = Post::avg('rating');
$totalViews = Post::sum('views');

// 6. Find by ID
$post = Post::find(1);
$post = Post::findOrFail(1);

// 7. Complex queries
$posts = Post::query()
    ->with(['user', 'comments' => function($query) {
        $query->where('approved', true)
              ->orderBy('created_at', 'desc')
              ->limit(5);
    }])
    ->where('status', 'published')
    ->whereDate('created_at', '>', now()->subDays(7))
    ->orderBy('views', 'desc')
    ->paginate(20);

// 8. Error handling
try {
    $posts = Post::where('status', 'published')->get();
} catch (\RemoteEloquent\Client\Exceptions\RemoteQueryException $e) {
    logger()->error('Remote query failed', [
        'error' => $e->getMessage(),
        'status_code' => $e->getStatusCode(),
        'context' => $e->getContext(),
    ]);

    $posts = collect([]);
}
