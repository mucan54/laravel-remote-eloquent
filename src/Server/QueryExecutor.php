<?php

namespace RemoteEloquent\Server;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Query Executor
 *
 * Executes remote queries safely on the server.
 */
class QueryExecutor
{
    /**
     * Execute query from AST
     *
     * @param array $ast
     * @return mixed
     */
    public function execute(array $ast)
    {
        // Validate model
        $modelClass = $this->resolveModel($ast['model']);
        $this->validateModel($modelClass);

        // Start query (Global Scopes apply here automatically!)
        $query = $modelClass::query();

        // Apply chain methods
        foreach ($ast['chain'] as $link) {
            $this->validateMethod($link['method']);
            $params = $this->deserializeParameters($link['parameters'] ?? []);
            $query = $query->{$link['method']}(...$params);
        }

        // Execute terminal method
        $this->validateMethod($ast['method']);
        $params = $this->deserializeParameters($ast['parameters'] ?? []);
        $result = $query->{$ast['method']}(...$params);

        // Serialize result
        return $this->serializeResult($result);
    }

    /**
     * Resolve model class
     *
     * @param string $modelName
     * @return string
     */
    protected function resolveModel(string $modelName): string
    {
        $namespaces = ['App\\Models\\', 'App\\'];

        foreach ($namespaces as $namespace) {
            $class = $namespace . $modelName;
            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \Exception("Model '{$modelName}' not found");
    }

    /**
     * Validate model is allowed
     *
     * @param string $modelClass
     * @return void
     */
    protected function validateModel(string $modelClass): void
    {
        $allowed = config('remote-eloquent.allowed_models', []);

        if (empty($allowed)) {
            // If no whitelist, deny all
            throw new \Exception("Model whitelist is empty. Configure allowed_models.");
        }

        $modelName = class_basename($modelClass);
        if (!in_array($modelName, $allowed) && !in_array($modelClass, $allowed)) {
            throw new \Exception("Model '{$modelName}' is not allowed");
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \Exception("'{$modelName}' is not an Eloquent model");
        }
    }

    /**
     * Validate method is allowed
     *
     * @param string $method
     * @return void
     */
    protected function validateMethod(string $method): void
    {
        $chainMethods = config('remote-eloquent.allowed_methods.chain', [
            'where', 'orWhere', 'whereIn', 'whereNotIn', 'whereNull', 'whereNotNull',
            'with', 'withCount', 'has', 'whereHas', 'doesntHave',
            'orderBy', 'orderByDesc', 'latest', 'oldest',
            'limit', 'take', 'skip', 'select', 'groupBy',
        ]);

        $terminalMethods = config('remote-eloquent.allowed_methods.terminal', [
            'get', 'first', 'find', 'findOrFail', 'count', 'sum', 'avg', 'max', 'min',
            'exists', 'doesntExist', 'pluck', 'value', 'paginate', 'simplePaginate',
        ]);

        $allowed = array_merge($chainMethods, $terminalMethods);

        if (!in_array($method, $allowed)) {
            throw new \Exception("Method '{$method}' is not allowed");
        }

        // Forbidden methods
        $forbidden = ['raw', 'rawQuery', 'truncate', 'delete', 'update', 'create'];
        if (in_array($method, $forbidden)) {
            throw new \Exception("Method '{$method}' is forbidden");
        }
    }

    /**
     * Deserialize parameters
     *
     * @param array $parameters
     * @return array
     */
    protected function deserializeParameters(array $parameters): array
    {
        return array_map(function ($param) {
            if (is_array($param) && isset($param['__type__'])) {
                return match($param['__type__']) {
                    'DateTime' => \Illuminate\Support\Carbon::parse($param['value'], $param['timezone'] ?? 'UTC'),
                    'Closure' => $this->deserializeClosure($param),
                    default => $param,
                };
            }
            return $param;
        }, $parameters);
    }

    /**
     * Deserialize closure
     *
     * @param array $data
     * @return \Closure
     */
    protected function deserializeClosure(array $data): \Closure
    {
        $chain = $data['chain'] ?? [];

        return function ($query) use ($chain) {
            foreach ($chain as $link) {
                $params = $this->deserializeParameters($link['parameters'] ?? []);
                $query->{$link['method']}(...$params);
            }
        };
    }

    /**
     * Serialize result
     *
     * @param mixed $result
     * @return mixed
     */
    protected function serializeResult($result)
    {
        if ($result instanceof Collection) {
            return $result->map(fn($item) => $item instanceof Model ? $item->toArray() : $item)->all();
        }

        if ($result instanceof LengthAwarePaginator) {
            return [
                'data' => collect($result->items())->map(fn($item) => $item instanceof Model ? $item->toArray() : $item)->all(),
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ];
        }

        if ($result instanceof Model) {
            return $result->toArray();
        }

        return $result;
    }
}
