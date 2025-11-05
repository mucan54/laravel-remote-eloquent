<?php

namespace RemoteEloquent;

use Illuminate\Database\Eloquent\Model;
use RemoteEloquent\Client\RemoteQueryBuilder;

/**
 * Remote Eloquent Model
 *
 * Unified model that works in both client (NativePHP) and server (Backend) modes.
 *
 * Client Mode (REMOTE_ELOQUENT_MODE=client):
 *   - Queries are sent to remote API
 *   - Uses RemoteQueryBuilder
 *
 * Server Mode (REMOTE_ELOQUENT_MODE=server):
 *   - Queries execute locally on database
 *   - Uses normal Eloquent
 *   - Global Scopes apply automatically
 *
 * Usage:
 * ```php
 * class Post extends RemoteModel
 * {
 *     // That's it! Works in both modes
 * }
 *
 * // NativePHP (client): Sends API request
 * // Backend (server): Queries local database
 * $posts = Post::where('status', 'published')->get();
 * ```
 */
abstract class RemoteModel extends Model
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * This is the magic method that switches between remote and local queries.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder|RemoteQueryBuilder
     */
    public function newEloquentBuilder($query)
    {
        // Check mode from config
        $mode = config('remote-eloquent.mode', 'server');

        if ($mode === 'client') {
            // Client mode: Use RemoteQueryBuilder
            return new RemoteQueryBuilder($this);
        }

        // Server mode: Use normal Eloquent (Global Scopes apply here!)
        return parent::newEloquentBuilder($query);
    }

    /**
     * Check if running in client mode
     *
     * @return bool
     */
    public static function isClientMode(): bool
    {
        return config('remote-eloquent.mode') === 'client';
    }

    /**
     * Check if running in server mode
     *
     * @return bool
     */
    public static function isServerMode(): bool
    {
        return config('remote-eloquent.mode') === 'server';
    }
}
