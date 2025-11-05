<?php

namespace RemoteEloquent\Server\Traits;

/**
 * Remote Queryable Trait
 *
 * Add this trait to models that should be queryable via remote eloquent.
 * This is used when security.strategy is set to 'trait'.
 *
 * Usage:
 * ```php
 * class Post extends Model
 * {
 *     use RemoteQueryable;
 * }
 * ```
 */
trait RemoteQueryable
{
    /**
     * Indicates that this model can be queried remotely
     *
     * @var bool
     */
    protected bool $remoteQueryable = true;

    /**
     * Check if model is remotely queryable
     *
     * @return bool
     */
    public function isRemoteQueryable(): bool
    {
        return $this->remoteQueryable ?? true;
    }
}
