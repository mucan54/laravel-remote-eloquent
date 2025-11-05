<?php

namespace RemoteEloquent\Client;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Remote Query Builder
 *
 * Captures Eloquent method calls and sends them to remote API as AST.
 */
class RemoteQueryBuilder extends Builder
{
    /**
     * Captured method chain
     *
     * @var array
     */
    protected array $methodChain = [];

    /**
     * Terminal methods that execute the query
     *
     * @var array
     */
    protected array $terminalMethods = [
        'get', 'first', 'find', 'findOrFail', 'count', 'sum', 'avg', 'max', 'min',
        'exists', 'doesntExist', 'pluck', 'value', 'paginate', 'simplePaginate',
    ];

    /**
     * Capture method calls
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Check if terminal method
        if (in_array($method, $this->terminalMethods)) {
            return $this->executeRemoteQuery($method, $parameters);
        }

        // Capture chain method
        $this->methodChain[] = [
            'method' => $method,
            'parameters' => $this->serializeParameters($parameters),
        ];

        return $this;
    }

    /**
     * Execute query on remote API
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    protected function executeRemoteQuery(string $method, array $parameters)
    {
        $modelClass = get_class($this->model);
        $modelName = class_basename($modelClass);

        $ast = [
            'model' => $modelName,
            'chain' => $this->methodChain,
            'method' => $method,
            'parameters' => $this->serializeParameters($parameters),
        ];

        $apiUrl = config('remote-eloquent.api_url');
        $token = $this->getAuthToken();

        $response = Http::timeout(30)
            ->withToken($token)
            ->post("{$apiUrl}/api/remote-eloquent/execute", $ast);

        if (!$response->successful()) {
            throw new \Exception("Remote query failed: " . $response->body());
        }

        $data = $response->json('data');

        // Transform result
        return match($method) {
            'get' => $this->transformCollection($data),
            'paginate' => $this->transformPaginator($data, $parameters),
            'first', 'find', 'findOrFail' => $data ? (object) $data : null,
            default => $data,
        };
    }

    /**
     * Serialize parameters
     *
     * @param array $parameters
     * @return array
     */
    protected function serializeParameters(array $parameters): array
    {
        return array_map(function ($param) {
            if ($param instanceof \Closure) {
                return $this->serializeClosure($param);
            }
            if ($param instanceof \DateTimeInterface) {
                return [
                    '__type__' => 'DateTime',
                    'value' => $param->format('Y-m-d H:i:s'),
                    'timezone' => $param->getTimezone()->getName(),
                ];
            }
            return $param;
        }, $parameters);
    }

    /**
     * Serialize closure
     *
     * @param \Closure $closure
     * @return array
     */
    protected function serializeClosure(\Closure $closure): array
    {
        $captureBuilder = new ClosureCaptureBuilder();
        $closure($captureBuilder);

        return [
            '__type__' => 'Closure',
            'chain' => $captureBuilder->getCapturedChain(),
        ];
    }

    /**
     * Transform to collection
     *
     * @param array|null $data
     * @return Collection
     */
    protected function transformCollection(?array $data): Collection
    {
        if (!$data) return collect([]);

        return collect($data)->map(fn($item) => is_array($item) ? (object) $item : $item);
    }

    /**
     * Transform to paginator
     *
     * @param array $data
     * @param array $parameters
     * @return LengthAwarePaginator
     */
    protected function transformPaginator(array $data, array $parameters): LengthAwarePaginator
    {
        $items = $this->transformCollection($data['data'] ?? []);

        return new LengthAwarePaginator(
            $items,
            $data['total'] ?? 0,
            $data['per_page'] ?? $parameters[0] ?? 15,
            $data['current_page'] ?? 1
        );
    }

    /**
     * Get authentication token
     *
     * @return string|null
     */
    protected function getAuthToken(): ?string
    {
        return cache(config('remote-eloquent.auth.cache_key', 'remote_eloquent_token'));
    }

    /**
     * Get the captured method chain (for batch queries)
     *
     * @return array
     */
    public function getMethodChain(): array
    {
        return $this->methodChain;
    }
}

/**
 * Closure Capture Builder
 */
class ClosureCaptureBuilder
{
    protected array $chain = [];

    public function __call(string $method, array $parameters)
    {
        $this->chain[] = ['method' => $method, 'parameters' => $parameters];
        return $this;
    }

    public function getCapturedChain(): array
    {
        return $this->chain;
    }
}
