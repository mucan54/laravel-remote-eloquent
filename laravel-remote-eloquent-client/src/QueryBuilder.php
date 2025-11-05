<?php

namespace RemoteEloquent\Client;

use RemoteEloquent\Client\Serializers\ParameterSerializer;
use RemoteEloquent\Client\Exceptions\RemoteQueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Query Builder
 *
 * Captures Eloquent method calls and converts them to an Abstract Syntax Tree (AST)
 * for secure transmission to the remote backend.
 */
class QueryBuilder
{
    /**
     * Model class name
     *
     * @var string
     */
    protected string $model;

    /**
     * Chain of method calls
     *
     * @var array
     */
    protected array $chain = [];

    /**
     * API base URL
     *
     * @var string
     */
    protected string $apiUrl;

    /**
     * Terminal methods that execute the query
     *
     * @var array
     */
    protected array $terminalMethods = [
        'get', 'first', 'find', 'findOrFail',
        'count', 'sum', 'avg', 'max', 'min',
        'exists', 'doesntExist',
        'pluck', 'value',
        'paginate', 'simplePaginate', 'cursorPaginate',
    ];

    /**
     * Create a new query builder instance
     *
     * @param string $model
     */
    public function __construct(string $model)
    {
        $this->model = $model;
        $this->apiUrl = config('remote-eloquent.api_url');
    }

    /**
     * Create a new query builder for the given model
     *
     * @param string $model
     * @return static
     */
    public static function for(string $model): static
    {
        return new static($model);
    }

    /**
     * Magic method to capture all method calls
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        // Check if this is a terminal method (executes the query)
        if ($this->isTerminalMethod($method)) {
            return $this->executeQuery($method, $parameters);
        }

        // Add to chain for later execution
        $this->chain[] = [
            'method' => $method,
            'parameters' => ParameterSerializer::serialize($parameters),
        ];

        return $this; // Enable method chaining
    }

    /**
     * Check if method is a terminal method
     *
     * @param string $method
     * @return bool
     */
    protected function isTerminalMethod(string $method): bool
    {
        return in_array($method, $this->terminalMethods);
    }

    /**
     * Execute the query on the remote backend
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     * @throws RemoteQueryException
     */
    protected function executeQuery(string $method, array $parameters)
    {
        $ast = $this->buildAST($method, $parameters);

        try {
            $response = $this->sendRequest($ast);

            if (!$response->successful()) {
                throw RemoteQueryException::fromResponse($response, ['ast' => $ast]);
            }

            $data = $response->json('data');

            return $this->transformResult($data, $method, $parameters);
        } catch (RemoteQueryException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RemoteQueryException(
                "Remote query failed: {$e->getMessage()}",
                0,
                $e,
                ['ast' => $ast]
            );
        }
    }

    /**
     * Build Abstract Syntax Tree for the query
     *
     * @param string $method
     * @param array $parameters
     * @return array
     */
    protected function buildAST(string $method, array $parameters): array
    {
        return [
            'model' => $this->model,
            'chain' => $this->chain,
            'method' => $method,
            'parameters' => ParameterSerializer::serialize($parameters),
            'metadata' => [
                'client_version' => $this->getClientVersion(),
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Send HTTP request to backend
     *
     * @param array $ast
     * @return \Illuminate\Http\Client\Response
     * @throws RemoteQueryException
     */
    protected function sendRequest(array $ast)
    {
        $token = $this->getAuthToken();
        $timeout = config('remote-eloquent.request.timeout', 30);

        $request = Http::timeout($timeout);

        if ($token) {
            $request->withToken($token);
        }

        // Add retry logic if enabled
        if (config('remote-eloquent.request.retry.enabled', true)) {
            $times = config('remote-eloquent.request.retry.times', 3);
            $request->retry($times, 100, function ($exception, $request) {
                // Only retry on network errors, not on 4xx errors
                return !($exception instanceof \Illuminate\Http\Client\RequestException) ||
                       $exception->response->status() >= 500;
            });
        }

        try {
            return $request->post("{$this->apiUrl}/api/remote-eloquent/execute", $ast);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw RemoteQueryException::networkError($e->getMessage(), ['ast' => $ast]);
        }
    }

    /**
     * Transform result based on method type
     *
     * @param mixed $data
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    protected function transformResult($data, string $method, array $parameters)
    {
        return match ($method) {
            'get' => $this->toCollection($data),
            'paginate', 'simplePaginate' => $this->toPaginator($data, $method, $parameters),
            'first', 'find', 'findOrFail' => $data ? (object) $data : null,
            'pluck' => collect($data),
            'count', 'sum', 'avg', 'max', 'min' => $data,
            'exists' => (bool) $data,
            'doesntExist' => !(bool) $data,
            'value' => $data,
            default => $data,
        };
    }

    /**
     * Convert array to Collection
     *
     * @param array|null $data
     * @return Collection
     */
    protected function toCollection(?array $data): Collection
    {
        if (!$data) {
            return collect([]);
        }

        return collect($data)->map(function ($item) {
            return is_array($item) ? (object) $item : $item;
        });
    }

    /**
     * Convert array to Paginator
     *
     * @param array $data
     * @param string $method
     * @param array $parameters
     * @return LengthAwarePaginator|Paginator
     */
    protected function toPaginator(array $data, string $method, array $parameters)
    {
        $items = collect($data['data'] ?? [])->map(function ($item) {
            return is_array($item) ? (object) $item : $item;
        });

        if ($method === 'simplePaginate') {
            return new Paginator(
                $items,
                $data['per_page'] ?? $parameters[0] ?? 15,
                $data['current_page'] ?? 1
            );
        }

        return new LengthAwarePaginator(
            $items,
            $data['total'] ?? 0,
            $data['per_page'] ?? $parameters[0] ?? 15,
            $data['current_page'] ?? 1,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Get authentication token
     *
     * @return string|null
     */
    protected function getAuthToken(): ?string
    {
        $driver = config('remote-eloquent.auth.driver', 'cache');

        if ($driver === 'cache') {
            $key = config('remote-eloquent.auth.cache_key', 'remote_eloquent_token');
            return cache($key);
        }

        if ($driver === 'session') {
            return session(config('remote-eloquent.auth.session_key', 'remote_eloquent_token'));
        }

        if ($driver === 'config') {
            return config('remote-eloquent.auth.token');
        }

        return null;
    }

    /**
     * Get client version
     *
     * @return string
     */
    protected function getClientVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get the AST representation (for debugging)
     *
     * @return array
     */
    public function toAST(string $method = 'get', array $parameters = []): array
    {
        return $this->buildAST($method, $parameters);
    }

    /**
     * Dump the AST and die (for debugging)
     *
     * @return void
     */
    public function dd(): void
    {
        dd($this->toAST());
    }

    /**
     * Dump the AST (for debugging)
     *
     * @return $this
     */
    public function dump(): self
    {
        dump($this->toAST());
        return $this;
    }
}
