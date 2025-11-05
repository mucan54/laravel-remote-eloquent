<?php

namespace RemoteEloquent\Server\Security;

use RemoteEloquent\Server\Exceptions\SecurityException;

/**
 * Method Validator
 *
 * Ensures only whitelisted methods can be executed via remote queries.
 * This is a critical security layer that prevents unauthorized method calls.
 */
class MethodValidator
{
    /**
     * Validate a chain method (non-terminal)
     *
     * @param string $method
     * @return void
     * @throws SecurityException
     */
    public function validateChainMethod(string $method): void
    {
        $allowed = config('remote-eloquent.allowed_methods.chain', $this->getDefaultChainMethods());

        if (!in_array($method, $allowed, true)) {
            throw new SecurityException(
                "Method '{$method}' is not allowed in query chain",
                403
            );
        }
    }

    /**
     * Validate a terminal method (executes query)
     *
     * @param string $method
     * @return void
     * @throws SecurityException
     */
    public function validateTerminalMethod(string $method): void
    {
        $allowed = config('remote-eloquent.allowed_methods.terminal', $this->getDefaultTerminalMethods());

        if (!in_array($method, $allowed, true)) {
            throw new SecurityException(
                "Method '{$method}' is not allowed as terminal method",
                403
            );
        }
    }

    /**
     * Get default allowed chain methods
     *
     * @return array
     */
    protected function getDefaultChainMethods(): array
    {
        return [
            // Where clauses
            'where', 'orWhere', 'whereIn', 'whereNotIn', 'whereBetween', 'whereNotBetween',
            'whereNull', 'whereNotNull', 'whereDate', 'whereMonth', 'whereDay', 'whereYear',
            'whereTime', 'whereColumn', 'whereLike',

            // Relationships
            'with', 'withCount', 'withSum', 'withAvg', 'withMin', 'withMax',
            'has', 'orHas', 'doesntHave', 'orDoesntHave',
            'whereHas', 'orWhereHas', 'whereDoesntHave', 'orWhereDoesntHave',

            // Ordering
            'orderBy', 'orderByDesc', 'latest', 'oldest', 'inRandomOrder',

            // Limiting
            'limit', 'take', 'skip', 'offset',

            // Selecting
            'select', 'addSelect', 'distinct',

            // Grouping
            'groupBy', 'having', 'havingRaw',

            // Joins (be cautious with these)
            'join', 'leftJoin', 'rightJoin',

            // Scopes
            'withoutGlobalScope', 'withoutGlobalScopes',
        ];
    }

    /**
     * Get default allowed terminal methods
     *
     * @return array
     */
    protected function getDefaultTerminalMethods(): array
    {
        return [
            // Reading
            'get', 'first', 'find', 'findOrFail', 'sole',
            'value', 'pluck',
            'count', 'sum', 'avg', 'average', 'max', 'min',
            'exists', 'doesntExist',

            // Pagination
            'paginate', 'simplePaginate', 'cursorPaginate',

            // Aggregates
            'toSql', 'dump', 'dd',

            // Writing (DISABLED by default - uncomment only if you understand the risks)
            // 'create', 'insert', 'insertGetId', 'insertOrIgnore',
            // 'update', 'updateOrCreate', 'upsert',
            // 'delete', 'forceDelete',
            // 'increment', 'decrement',
        ];
    }

    /**
     * Check if method is forbidden
     *
     * @param string $method
     * @return bool
     */
    public function isForbiddenMethod(string $method): bool
    {
        $forbidden = [
            'raw', 'rawQuery', 'selectRaw', 'whereRaw', 'orWhereRaw',
            'havingRaw', 'orHavingRaw', 'orderByRaw',
            'truncate', 'drop', 'dropIfExists',
            'exec', 'shell_exec', 'eval', 'system', 'passthru',
        ];

        return in_array($method, $forbidden, true);
    }
}
