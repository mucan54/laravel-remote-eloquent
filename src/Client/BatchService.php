<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\Facades\Http;

/**
 * Batch Service with Conditional/Dependency Execution
 *
 * Execute multiple service methods with dependency management and conditional logic.
 * Works in BOTH modes - same code everywhere!
 *
 * Simple Usage:
 * ```php
 * $results = BatchService::run([
 *     'charge' => [$paymentService, 'processPayment', [1000, $token]],
 *     'email' => [$emailService, 'sendReceipt', [$userId, $orderId]],
 * ]);
 * ```
 *
 * Advanced Usage with Dependencies:
 * ```php
 * $results = BatchService::run([
 *     'payment' => [$paymentService, 'charge', [1000, $token]],
 *     'email' => [
 *         'service' => $emailService,
 *         'method' => 'sendReceipt',
 *         'args' => fn($results) => [$userId, $results['payment']['orderId']],
 *         'depends_on' => ['payment'],
 *         'on_failure' => 'skip', // skip|stop|continue
 *     ],
 *     'sms' => [
 *         'service' => $smsService,
 *         'method' => 'sendConfirmation',
 *         'args' => fn($results) => [$phone, $results['payment']['orderId']],
 *         'depends_on' => ['payment'],
 *         'on_failure' => 'skip',
 *     ],
 * ]);
 * ```
 */
class BatchService
{
    /**
     * Execute batch service calls with dependency resolution
     *
     * @param array $services Array of service configurations
     * @return array Results keyed by service keys
     * @throws \Exception
     */
    public static function run(array $services): array
    {
        // Check mode
        $mode = config('remote-eloquent.mode', 'server');

        if ($mode === 'client') {
            return static::executeRemote($services);
        }

        // Server mode: execute locally
        return static::executeLocal($services);
    }

    /**
     * Execute batch on remote server
     *
     * @param array $services
     * @return array
     * @throws \Exception
     */
    protected static function executeRemote(array $services): array
    {
        // Build batch AST with dependency information
        $batch = [];
        foreach ($services as $key => $item) {
            $parsed = static::parseServiceItem($item);

            $batch[$key] = [
                'service' => is_object($parsed['service']) ? get_class($parsed['service']) : $parsed['service'],
                'method' => $parsed['method'],
                'arguments' => static::serializeArguments($parsed['args']),
                'depends_on' => $parsed['depends_on'] ?? [],
                'on_failure' => $parsed['on_failure'] ?? 'stop',
                'has_closure_args' => $parsed['has_closure_args'],
            ];
        }

        // Send to server
        $apiUrl = config('remote-eloquent.api_url');
        $token = cache(config('remote-eloquent.auth.cache_key', 'remote_eloquent_token'));

        $response = Http::timeout(60)
            ->withToken($token)
            ->post("{$apiUrl}/api/remote-eloquent/batch-service", ['services' => $batch]);

        if (!$response->successful()) {
            throw new \Exception("Batch service execution failed: " . $response->body());
        }

        $results = $response->json('data');

        // Deserialize results
        return array_map([static::class, 'deserializeResult'], $results);
    }

    /**
     * Execute batch locally (server mode) with dependency resolution
     *
     * @param array $services
     * @return array
     * @throws \Exception
     */
    protected static function executeLocal(array $services): array
    {
        // Parse all services
        $parsed = [];
        foreach ($services as $key => $item) {
            $parsed[$key] = static::parseServiceItem($item);
        }

        // Validate dependencies
        static::validateDependencies($parsed);

        // Sort by dependencies (topological sort)
        $executionOrder = static::topologicalSort($parsed);

        // Execute in order
        $results = [];
        $failed = [];
        $stopped = false;

        foreach ($executionOrder as $key) {
            // Check if stopped
            if ($stopped) {
                $results[$key] = ['error' => 'Execution stopped due to previous failure'];
                continue;
            }

            $config = $parsed[$key];
            $onFailure = $config['on_failure'] ?? 'stop';

            // Check if dependencies succeeded
            $dependenciesFailed = false;
            foreach ($config['depends_on'] ?? [] as $dep) {
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
                // Resolve arguments (handle closures)
                $args = $config['args'];
                if ($config['has_closure_args'] && is_callable($args)) {
                    $args = $args($results);
                }

                // Ensure args is array
                if (!is_array($args)) {
                    $args = [$args];
                }

                // Execute method
                $service = $config['service'];
                $method = $config['method'];

                $results[$key] = $service->$method(...$args);
            } catch (\Exception $e) {
                $results[$key] = ['error' => $e->getMessage()];
                $failed[] = $key;

                // Handle failure
                if ($onFailure === 'stop') {
                    $stopped = true;
                }
                // skip and continue: just record the failure
            }
        }

        return $results;
    }

    /**
     * Parse service item into normalized format
     *
     * Supports both formats:
     * 1. Simple: [$service, 'method', $args]
     * 2. Extended: ['service' => $service, 'method' => '...', 'args' => ..., 'depends_on' => [], 'on_failure' => '...']
     *
     * @param mixed $item
     * @return array Normalized config
     * @throws \InvalidArgumentException
     */
    protected static function parseServiceItem($item): array
    {
        // Extended format (array with keys)
        if (is_array($item) && isset($item['service']) && isset($item['method'])) {
            $args = $item['args'] ?? [];

            return [
                'service' => $item['service'],
                'method' => $item['method'],
                'args' => $args,
                'depends_on' => $item['depends_on'] ?? [],
                'on_failure' => $item['on_failure'] ?? 'stop',
                'has_closure_args' => is_callable($args),
            ];
        }

        // Simple format (indexed array)
        if (is_array($item) && count($item) >= 2 && isset($item[0]) && isset($item[1])) {
            return [
                'service' => $item[0],
                'method' => $item[1],
                'args' => $item[2] ?? [],
                'depends_on' => [],
                'on_failure' => 'stop',
                'has_closure_args' => false,
            ];
        }

        throw new \InvalidArgumentException("Invalid service item format");
    }

    /**
     * Validate dependencies (check for circular dependencies and missing dependencies)
     *
     * @param array $services Parsed services
     * @throws \Exception
     */
    protected static function validateDependencies(array $services): void
    {
        foreach ($services as $key => $config) {
            foreach ($config['depends_on'] ?? [] as $dep) {
                // Check if dependency exists
                if (!isset($services[$dep])) {
                    throw new \Exception("Service '{$key}' depends on non-existent service '{$dep}'");
                }

                // Check for circular dependencies (simple check)
                if (static::hasCircularDependency($services, $key, $dep, [])) {
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
    protected static function hasCircularDependency(array $services, string $start, string $current, array $visited): bool
    {
        if ($current === $start && !empty($visited)) {
            return true;
        }

        if (in_array($current, $visited)) {
            return false;
        }

        $visited[] = $current;

        foreach ($services[$current]['depends_on'] ?? [] as $dep) {
            if (static::hasCircularDependency($services, $start, $dep, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Topological sort - order services by dependencies
     *
     * @param array $services Parsed services
     * @return array Execution order (array of keys)
     */
    protected static function topologicalSort(array $services): array
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
     * Serialize arguments (handle DateTime, objects, closures)
     *
     * @param mixed $arguments
     * @return mixed
     */
    protected static function serializeArguments($arguments)
    {
        // If it's a closure, we can't serialize it for remote execution
        if (is_callable($arguments) && !is_array($arguments)) {
            throw new \Exception("Closure arguments are not supported in client mode. Use server mode for dependency-based execution with closures.");
        }

        if (!is_array($arguments)) {
            return $arguments;
        }

        return array_map(function ($arg) {
            if ($arg instanceof \DateTimeInterface) {
                return [
                    '__type__' => 'DateTime',
                    'value' => $arg->format('Y-m-d H:i:s'),
                    'timezone' => $arg->getTimezone()->getName(),
                ];
            }

            if (is_object($arg) && method_exists($arg, 'toArray')) {
                return [
                    '__type__' => 'Object',
                    'class' => get_class($arg),
                    'data' => $arg->toArray(),
                ];
            }

            return $arg;
        }, $arguments);
    }

    /**
     * Deserialize result
     *
     * @param mixed $result
     * @return mixed
     */
    protected static function deserializeResult($result)
    {
        if (is_array($result)) {
            if (isset($result['__type__']) && $result['__type__'] === 'DateTime') {
                return \Illuminate\Support\Carbon::parse($result['value'], $result['timezone'] ?? 'UTC');
            }

            if (isset($result['__type__']) && $result['__type__'] === 'Collection') {
                return collect($result['data']);
            }

            return array_map([static::class, 'deserializeResult'], $result);
        }

        return $result;
    }
}
