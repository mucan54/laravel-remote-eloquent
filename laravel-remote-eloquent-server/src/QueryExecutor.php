<?php

namespace RemoteEloquent\Server;

use RemoteEloquent\Server\Deserializers\ParameterDeserializer;
use RemoteEloquent\Server\Security\MethodValidator;
use RemoteEloquent\Server\Security\ModelValidator;
use RemoteEloquent\Server\Exceptions\QueryExecutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Query Executor
 *
 * Reconstructs Eloquent queries from AST and executes them safely.
 * This is the core component that applies all security validations
 * and ensures Global Scopes work automatically.
 */
class QueryExecutor
{
    /**
     * Model validator instance
     *
     * @var ModelValidator
     */
    protected ModelValidator $modelValidator;

    /**
     * Method validator instance
     *
     * @var MethodValidator
     */
    protected MethodValidator $methodValidator;

    /**
     * Parameter deserializer instance
     *
     * @var ParameterDeserializer
     */
    protected ParameterDeserializer $parameterDeserializer;

    /**
     * Create a new query executor instance
     *
     * @param ModelValidator $modelValidator
     * @param MethodValidator $methodValidator
     * @param ParameterDeserializer $parameterDeserializer
     */
    public function __construct(
        ModelValidator $modelValidator,
        MethodValidator $methodValidator,
        ParameterDeserializer $parameterDeserializer
    ) {
        $this->modelValidator = $modelValidator;
        $this->methodValidator = $methodValidator;
        $this->parameterDeserializer = $parameterDeserializer;
    }

    /**
     * Execute query from AST
     *
     * @param array $ast
     * @param mixed $user
     * @return mixed
     * @throws QueryExecutionException
     */
    public function execute(array $ast, $user = null)
    {
        $startTime = microtime(true);

        try {
            // Validate AST structure
            $this->validateAST($ast);

            // Validate and resolve model
            $modelClass = $this->modelValidator->validate($ast['model']);

            // Start query builder
            // IMPORTANT: Global Scopes are applied automatically here!
            $query = $modelClass::query();

            // Apply chain methods
            foreach ($ast['chain'] as $link) {
                $this->validateChainMethod($link['method']);
                $params = $this->parameterDeserializer->deserialize($link['parameters'] ?? []);
                $query = $query->{$link['method']}(...$params);
            }

            // Execute terminal method
            $this->methodValidator->validateTerminalMethod($ast['method']);
            $params = $this->parameterDeserializer->deserialize($ast['parameters'] ?? []);
            $result = $query->{$ast['method']}(...$params);

            // Serialize result
            $serialized = $this->serializeResult($result, $ast['method']);

            // Log if enabled
            $this->logQuery($ast, $startTime, $user);

            return $serialized;

        } catch (QueryExecutionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                "Query execution failed: {$e->getMessage()}",
                $e->getCode(),
                $e,
                ['ast' => $ast]
            );
        }
    }

    /**
     * Validate AST structure
     *
     * @param array $ast
     * @return void
     * @throws QueryExecutionException
     */
    protected function validateAST(array $ast): void
    {
        if (!isset($ast['model']) || !is_string($ast['model'])) {
            throw new QueryExecutionException("AST must contain a valid 'model' field");
        }

        if (!isset($ast['chain']) || !is_array($ast['chain'])) {
            throw new QueryExecutionException("AST must contain a 'chain' array");
        }

        if (!isset($ast['method']) || !is_string($ast['method'])) {
            throw new QueryExecutionException("AST must contain a valid 'method' field");
        }

        // Validate chain structure
        foreach ($ast['chain'] as $index => $link) {
            if (!isset($link['method']) || !is_string($link['method'])) {
                throw new QueryExecutionException("Chain link at index {$index} must have a 'method' field");
            }
        }
    }

    /**
     * Validate chain method
     *
     * @param string $method
     * @return void
     * @throws QueryExecutionException
     */
    protected function validateChainMethod(string $method): void
    {
        // Check if method is forbidden
        if ($this->methodValidator->isForbiddenMethod($method)) {
            throw new QueryExecutionException("Method '{$method}' is forbidden for security reasons", 403);
        }

        // Validate against whitelist
        $this->methodValidator->validateChainMethod($method);
    }

    /**
     * Serialize query result
     *
     * @param mixed $result
     * @param string $method
     * @return mixed
     */
    protected function serializeResult($result, string $method)
    {
        // Collection of models
        if ($result instanceof Collection) {
            return $result->map(function ($item) {
                return $item instanceof Model ? $item->toArray() : $item;
            })->all();
        }

        // Paginated results
        if ($result instanceof LengthAwarePaginator) {
            return [
                'data' => collect($result->items())->map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                })->all(),
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
                'from' => $result->firstItem(),
                'to' => $result->lastItem(),
            ];
        }

        // Simple paginator
        if ($result instanceof Paginator) {
            return [
                'data' => collect($result->items())->map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                })->all(),
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
            ];
        }

        // Single model
        if ($result instanceof Model) {
            return $result->toArray();
        }

        // Collection (non-Eloquent)
        if ($result instanceof \Illuminate\Support\Collection) {
            return $result->all();
        }

        // Scalar or array
        return $result;
    }

    /**
     * Log query execution
     *
     * @param array $ast
     * @param float $startTime
     * @param mixed $user
     * @return void
     */
    protected function logQuery(array $ast, float $startTime, $user): void
    {
        if (!config('remote-eloquent.logging.enabled', false)) {
            return;
        }

        $executionTime = (microtime(true) - $startTime) * 1000; // milliseconds

        $logData = [
            'model' => $ast['model'],
            'method' => $ast['method'],
            'chain_length' => count($ast['chain']),
            'execution_time_ms' => round($executionTime, 2),
            'user_id' => $user?->id ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        // Log slow queries
        $slowThreshold = config('remote-eloquent.logging.slow_query_threshold', 1000);
        if (config('remote-eloquent.logging.log_slow_queries', true) && $executionTime > $slowThreshold) {
            Log::warning('Slow remote query detected', $logData);
        } else {
            Log::info('Remote query executed', $logData);
        }
    }
}
