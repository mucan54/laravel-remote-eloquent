<?php

namespace RemoteEloquent\Client;

/**
 * Remote Model
 *
 * Base class for models that execute queries on a remote Laravel backend.
 * Provides Eloquent-like API but sends requests to remote server.
 *
 * Usage:
 * ```php
 * class Post extends RemoteModel
 * {
 *     protected static string $remoteModel = 'Post';
 * }
 *
 * $posts = Post::where('status', 'published')->get();
 * ```
 */
abstract class RemoteModel
{
    /**
     * The remote model class name
     *
     * @var string
     */
    protected static string $remoteModel;

    /**
     * Get query builder for this model
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return QueryBuilder::for(static::getRemoteModelName());
    }

    /**
     * Get the remote model name
     *
     * @return string
     */
    protected static function getRemoteModelName(): string
    {
        if (isset(static::$remoteModel)) {
            return static::$remoteModel;
        }

        // Default: use class basename
        $class = static::class;
        return class_basename($class);
    }

    /**
     * Forward static calls to query builder
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return static::query()->$method(...$parameters);
    }

    // Common Eloquent methods for convenience

    /**
     * Get all models
     *
     * @param array|string $columns
     * @return \Illuminate\Support\Collection
     */
    public static function all($columns = ['*'])
    {
        return static::query()->get($columns);
    }

    /**
     * Find a model by ID
     *
     * @param mixed $id
     * @param array|string $columns
     * @return object|null
     */
    public static function find($id, $columns = ['*'])
    {
        return static::query()->find($id, $columns);
    }

    /**
     * Find a model by ID or fail
     *
     * @param mixed $id
     * @param array|string $columns
     * @return object
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        return static::query()->findOrFail($id, $columns);
    }

    /**
     * Begin a where clause
     *
     * @param mixed ...$args
     * @return QueryBuilder
     */
    public static function where(...$args): QueryBuilder
    {
        return static::query()->where(...$args);
    }

    /**
     * Begin an or where clause
     *
     * @param mixed ...$args
     * @return QueryBuilder
     */
    public static function orWhere(...$args): QueryBuilder
    {
        return static::query()->orWhere(...$args);
    }

    /**
     * Add a where in clause
     *
     * @param string $column
     * @param mixed $values
     * @return QueryBuilder
     */
    public static function whereIn(string $column, $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     * Eager load relationships
     *
     * @param mixed $relations
     * @return QueryBuilder
     */
    public static function with($relations): QueryBuilder
    {
        return static::query()->with($relations);
    }

    /**
     * Order by column
     *
     * @param string $column
     * @param string $direction
     * @return QueryBuilder
     */
    public static function orderBy(string $column, string $direction = 'asc'): QueryBuilder
    {
        return static::query()->orderBy($column, $direction);
    }

    /**
     * Get latest records
     *
     * @param string $column
     * @return QueryBuilder
     */
    public static function latest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->latest($column);
    }

    /**
     * Get oldest records
     *
     * @param string $column
     * @return QueryBuilder
     */
    public static function oldest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->oldest($column);
    }

    /**
     * Limit results
     *
     * @param int $value
     * @return QueryBuilder
     */
    public static function limit(int $value): QueryBuilder
    {
        return static::query()->limit($value);
    }

    /**
     * Alias for limit
     *
     * @param int $value
     * @return QueryBuilder
     */
    public static function take(int $value): QueryBuilder
    {
        return static::query()->take($value);
    }

    /**
     * Paginate results
     *
     * @param int $perPage
     * @param array|string $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function paginate(int $perPage = 15, $columns = ['*'], string $pageName = 'page', ?int $page = null)
    {
        return static::query()->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Count models
     *
     * @param string $columns
     * @return int
     */
    public static function count(string $columns = '*'): int
    {
        return static::query()->count($columns);
    }

    /**
     * Check if records exist
     *
     * @return bool
     */
    public static function exists(): bool
    {
        return static::query()->exists();
    }
}
