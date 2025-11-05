<?php

namespace RemoteEloquent\Server\Http\Controllers;

use RemoteEloquent\Server\QueryExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Remote Eloquent Controller
 *
 * API endpoint for executing remote queries.
 */
class RemoteEloquentController extends Controller
{
    public function __construct()
    {
        // Apply authentication middleware from config
        $authMiddleware = config('remote-eloquent.auth_middleware');

        if ($authMiddleware !== null) {
            // Support both string and array of middleware
            if (is_array($authMiddleware)) {
                foreach ($authMiddleware as $middleware) {
                    $this->middleware($middleware);
                }
            } else {
                $this->middleware($authMiddleware);
            }
        }

        // Rate limiting
        $this->middleware('throttle:100,1');
    }

    /**
     * Execute remote query
     *
     * @param Request $request
     * @param QueryExecutor $executor
     * @return JsonResponse
     */
    public function execute(Request $request, QueryExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'model' => 'required|string',
                'chain' => 'required|array',
                'method' => 'required|string',
                'parameters' => 'sometimes|array',
            ]);

            $result = $executor->execute($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute batch queries
     *
     * @param Request $request
     * @param QueryExecutor $executor
     * @return JsonResponse
     */
    public function batch(Request $request, QueryExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'queries' => 'required|array',
                'queries.*' => 'required|array',
            ]);

            // Check batch limit
            $maxBatch = config('remote-eloquent.batch.max_queries', 10);
            if (count($validated['queries']) > $maxBatch) {
                return response()->json([
                    'success' => false,
                    'error' => "Batch limit exceeded. Maximum {$maxBatch} queries allowed.",
                ], 400);
            }

            $results = [];
            $errors = [];

            // Execute each query
            foreach ($validated['queries'] as $key => $ast) {
                try {
                    $results[$key] = $executor->execute($ast);
                } catch (\Exception $e) {
                    $results[$key] = ['error' => $e->getMessage()];
                    $errors[] = $key;
                }
            }

            return response()->json([
                'success' => empty($errors),
                'data' => $results,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute remote service method
     *
     * @param Request $request
     * @param \RemoteEloquent\Server\ServiceExecutor $executor
     * @return JsonResponse
     */
    public function service(Request $request, \RemoteEloquent\Server\ServiceExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'service' => 'required|string',
                'method' => 'required|string',
                'arguments' => 'sometimes|array',
            ]);

            $result = $executor->execute($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute batch service methods with dependency resolution
     *
     * @param Request $request
     * @param \RemoteEloquent\Server\ServiceExecutor $executor
     * @return JsonResponse
     */
    public function batchService(Request $request, \RemoteEloquent\Server\ServiceExecutor $executor): JsonResponse
    {
        try {
            $validated = $request->validate([
                'services' => 'required|array',
                'services.*' => 'required|array',
            ]);

            // Check batch limit
            $maxBatch = config('remote-eloquent.batch.max_queries', 10);
            if (count($validated['services']) > $maxBatch) {
                return response()->json([
                    'success' => false,
                    'error' => "Batch limit exceeded. Maximum {$maxBatch} services allowed.",
                ], 400);
            }

            // Validate dependencies
            $this->validateServiceDependencies($validated['services']);

            // Get execution order (topological sort)
            $executionOrder = $this->topologicalSortServices($validated['services']);

            $results = [];
            $errors = [];
            $failed = [];
            $stopped = false;

            // Execute in dependency order
            foreach ($executionOrder as $key) {
                // Check if stopped
                if ($stopped) {
                    $results[$key] = ['error' => 'Execution stopped due to previous failure'];
                    continue;
                }

                $ast = $validated['services'][$key];
                $onFailure = $ast['on_failure'] ?? 'stop';
                $dependsOn = $ast['depends_on'] ?? [];

                // Check if dependencies succeeded
                $dependenciesFailed = false;
                foreach ($dependsOn as $dep) {
                    if (in_array($dep, $failed)) {
                        $dependenciesFailed = true;
                        break;
                    }
                }

                // Handle failed dependencies
                if ($dependenciesFailed) {
                    if ($onFailure === 'skip') {
                        $results[$key] = ['skipped' => true, 'reason' => 'Dependency failed'];
                        continue;
                    } elseif ($onFailure === 'stop') {
                        $results[$key] = ['error' => 'Dependency failed'];
                        $stopped = true;
                        continue;
                    }
                    // continue: try anyway
                }

                try {
                    // Note: Closures cannot be serialized from client to server
                    // They only work in server mode (local execution)
                    if ($ast['has_closure_args'] ?? false) {
                        throw new \Exception("Closure arguments are not supported in remote execution");
                    }

                    $results[$key] = $executor->execute($ast);
                } catch (\Exception $e) {
                    $results[$key] = ['error' => $e->getMessage()];
                    $errors[] = $key;
                    $failed[] = $key;

                    // Handle failure
                    if ($onFailure === 'stop') {
                        $stopped = true;
                    }
                }
            }

            return response()->json([
                'success' => empty($errors),
                'data' => $results,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate service dependencies
     *
     * @param array $services
     * @throws \Exception
     */
    protected function validateServiceDependencies(array $services): void
    {
        foreach ($services as $key => $config) {
            foreach ($config['depends_on'] ?? [] as $dep) {
                // Check if dependency exists
                if (!isset($services[$dep])) {
                    throw new \Exception("Service '{$key}' depends on non-existent service '{$dep}'");
                }

                // Check for circular dependencies
                if ($this->hasCircularServiceDependency($services, $key, $dep, [])) {
                    throw new \Exception("Circular dependency detected: '{$key}' <-> '{$dep}'");
                }
            }
        }
    }

    /**
     * Check for circular dependencies recursively
     *
     * @param array $services
     * @param string $start
     * @param string $current
     * @param array $visited
     * @return bool
     */
    protected function hasCircularServiceDependency(array $services, string $start, string $current, array $visited): bool
    {
        if ($current === $start && !empty($visited)) {
            return true;
        }

        if (in_array($current, $visited)) {
            return false;
        }

        $visited[] = $current;

        foreach ($services[$current]['depends_on'] ?? [] as $dep) {
            if ($this->hasCircularServiceDependency($services, $start, $dep, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Topological sort - order services by dependencies
     *
     * @param array $services
     * @return array Execution order (array of keys)
     */
    protected function topologicalSortServices(array $services): array
    {
        $sorted = [];
        $visited = [];
        $temp = [];

        $visit = function($key) use (&$visit, &$sorted, &$visited, &$temp, $services) {
            if (in_array($key, $temp)) {
                throw new \Exception("Circular dependency detected involving '{$key}'");
            }

            if (in_array($key, $visited)) {
                return;
            }

            $temp[] = $key;

            foreach ($services[$key]['depends_on'] ?? [] as $dep) {
                $visit($dep);
            }

            $temp = array_diff($temp, [$key]);
            $visited[] = $key;
            $sorted[] = $key;
        };

        foreach (array_keys($services) as $key) {
            $visit($key);
        }

        return $sorted;
    }

    /**
     * Health check
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'mode' => config('remote-eloquent.mode', 'server'),
        ]);
    }
}
