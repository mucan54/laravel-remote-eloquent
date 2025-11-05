<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RemoteEloquent\Server\Traits\RemoteQueryable;

/**
 * Example Eloquent Model for Server (Backend API)
 *
 * This model includes Global Scopes for Row Level Security.
 */
class Post extends Model
{
    use SoftDeletes, RemoteQueryable;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'title',
        'content',
        'status',
        'views',
        'rating',
    ];

    protected $casts = [
        'views' => 'integer',
        'rating' => 'float',
    ];

    /**
     * Global Scopes - Automatic Row Level Security
     *
     * These scopes are applied AUTOMATICALLY to ALL queries,
     * including remote queries from the mobile app.
     */
    protected static function booted()
    {
        // 1. User Isolation
        // Only show posts belonging to the authenticated user
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });

        // 2. Multi-Tenancy
        // Only show posts within the user's tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        // 3. Status Filter
        // Only show published posts
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('status', 'published');
        });
    }

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Local Scopes (can also be used remotely)
     */
    public function scopePopular(Builder $query, int $minViews = 100)
    {
        return $query->where('views', '>=', $minViews);
    }

    public function scopeHighRated(Builder $query, float $minRating = 4.0)
    {
        return $query->where('rating', '>=', $minRating);
    }
}

/**
 * When mobile app calls:
 *   Post::all()
 *
 * SQL executed on backend:
 *   SELECT * FROM posts
 *   WHERE deleted_at IS NULL          -- SoftDeletes (automatic)
 *     AND user_id = 123               -- Global scope 'user' (automatic)
 *     AND tenant_id = 5               -- Global scope 'tenant' (automatic)
 *     AND status = 'published'        -- Global scope 'published' (automatic)
 */
