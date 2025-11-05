<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\Facades\Http;

/**
 * Batch Query
 *
 * Execute multiple queries in a single HTTP request for better performance.
 *
 * Usage:
 * ```php
 * $results = BatchQuery::execute([
 *     'posts' => Post::where('status', 'published')->limit(10),
 *     'comments' => Comment::latest()->limit(5),
 *     'stats' => Post::where('status', 'published')->count(),
 * ]);
 *
 * $posts = $results['posts'];      // Collection
 * $comments = $results['comments']; // Collection
 * $stats = $results['stats'];       // int
 * ```
 */
class BatchQuery
{
    /**
     * Queries to execute
     *
     * @var array
     */
    protected array $queries = [];

    /**
     * Create a new batch query instance
     *
     * @return static
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Add a query to the batch
     *
     * @param string $key
     * @param \Illuminate\Database\Eloquent\Builder|RemoteQueryBuilder $query
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function add(string $key, $query, string $method = 'get', array $parameters = []): static
    {
        $this->queries[$key] = compact('query', 'method', 'parameters');
        return $this;
    }

    /**
     * Execute all queries and return results
     *
     * @return array
     * @throws \Exception
     */
    public function execute(): array
    {
        if (empty($this->queries)) {
            return [];
        }

        // Build ASTs for all queries
        $batch = [];
        foreach ($this->queries as $key => $item) {
            $batch[$key] = $this->buildQueryAST(
                $item['query'],
                $item['method'],
                $item['parameters']
            );
        }

        // Send batch request
        $apiUrl = config('remote-eloquent.api_url');
        $token = cache(config('remote-eloquent.auth.cache_key', 'remote_eloquent_token'));

        $response = Http::timeout(60) // Longer timeout for batch
            ->withToken($token)
            ->post("{$apiUrl}/api/remote-eloquent/batch", ['queries' => $batch]);

        if (!$response->successful()) {
            throw new \Exception("Batch query failed: " . $response->body());
        }

        $results = $response->json('data');

        // Transform results
        return $this->transformResults($results);
    }

    /**
     * Execute batch queries (static helper)
     *
     * @param array $queries ['key' => QueryBuilder]
     * @return array
     */
    public static function run(array $queries): array
    {
        $batch = static::new();

        foreach ($queries as $key => $query) {
            // Support both Builder instances and arrays with method/params
            if (is_array($query) && isset($query['query'], $query['method'])) {
                $batch->add($key, $query['query'], $query['method'], $query['parameters'] ?? []);
            } else {
                // Assume it's a builder with get() method
                $batch->add($key, $query, 'get');
            }
        }

        return $batch->execute();
    }

    /**
     * Build AST for a query
     *
     * @param mixed $query
     * @param string $method
     * @param array $parameters
     * @return array
     */
    protected function buildQueryAST($query, string $method, array $parameters): array
    {
        if ($query instanceof RemoteQueryBuilder) {
            $modelClass = get_class($query->getModel());
            $modelName = class_basename($modelClass);

            return [
                'model' => $modelName,
                'chain' => $query->getMethodChain(),
                'method' => $method,
                'parameters' => $this->serializeParameters($parameters),
            ];
        }

        // Regular Eloquent builder - extract info
        $model = $query->getModel();
        $modelName = class_basename($model);

        return [
            'model' => $modelName,
            'chain' => $this->extractChainFromBuilder($query),
            'method' => $method,
            'parameters' => $this->serializeParameters($parameters),
        ];
    }

    /**
     * Extract method chain from Eloquent builder
     *
     * @param mixed $query
     * @return array
     */
    protected function extractChainFromBuilder($query): array
    {
        // For RemoteQueryBuilder, get the chain directly
        if ($query instanceof RemoteQueryBuilder) {
            return $query->getMethodChain();
        }

        // For now, return empty chain for regular builders
        // In production, you might want to extract wheres, orders, etc.
        return [];
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
                // Serialize closures (simplified)
                return ['__type__' => 'Closure', 'chain' => []];
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
     * Transform results from server
     *
     * @param array $results
     * @return array
     */
    protected function transformResults(array $results): array
    {
        $transformed = [];

        foreach ($results as $key => $data) {
            if (isset($data['error'])) {
                $transformed[$key] = ['error' => $data['error']];
                continue;
            }

            // Transform based on result type
            $transformed[$key] = $this->transformResult($data);
        }

        return $transformed;
    }

    /**
     * Transform single result
     *
     * @param mixed $data
     * @return mixed
     */
    protected function transformResult($data)
    {
        if (is_array($data) && !empty($data)) {
            // Check if it's a collection of models
            if (isset($data[0]) && is_array($data[0])) {
                return collect($data)->map(fn($item) => (object) $item);
            }

            // Check if it's pagination data
            if (isset($data['data'], $data['current_page'])) {
                return $data; // Keep pagination structure
            }

            // Single model
            if (isset($data['id']) || count(array_filter(array_keys($data), 'is_string')) > 0) {
                return (object) $data;
            }
        }

        return $data;
    }
}
