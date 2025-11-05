<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\Facades\Http;

/**
 * Batch Service with Pipeline Pattern
 *
 * Execute multiple service methods with fluent pipeline interface.
 * Works in BOTH modes - same code everywhere!
 *
 * Pipeline Usage (Recommended):
 * ```php
 * $results = BatchService::pipeline()
 *     ->step('payment', [$paymentService, 'charge', [1000, $token]])
 *         ->stopOnFailure()
 *     ->step('email', [$emailService, 'send', fn($prev) => [$prev['payment']['orderId']]])
 *         ->skipOnFailure()
 *     ->step('sms', [$smsService, 'send', fn($prev) => [$prev['payment']['orderId']]])
 *         ->skipOnFailure()
 *     ->execute();
 * ```
 *
 * Array Usage (Backward Compatible):
 * ```php
 * $results = BatchService::run([
 *     'charge' => [$paymentService, 'processPayment', [1000, $token]],
 *     'email' => [$emailService, 'sendReceipt', [$userId, $orderId]],
 * ]);
 * ```
 */
class BatchService
{
    protected array $pipelineSteps = [];
    protected bool $isPipeline = false;
    protected string $currentStepKey = '';

    /**
     * Create a new pipeline instance
     *
     * @return static
     */
    public static function pipeline(): self
    {
        $instance = new self();
        $instance->isPipeline = true;
        return $instance;
    }

    /**
     * Add a step to the pipeline
     *
     * Supports multiple formats:
     * - ->step('key', [$service, 'method', $args])
     * - ->step('key', $service, 'method', $args)
     *
     * @param string $key Step identifier
     * @param mixed ...$config Service configuration
     * @return $this
     */
    public function step(string $key, ...$config): self
    {
        // Parse configuration
        if (count($config) === 1 && is_array($config[0])) {
            // Format: ->step('payment', [$service, 'method', $args])
            $serviceConfig = $config[0];
        } else if (count($config) >= 2) {
            // Format: ->step('payment', $service, 'method', $args)
            $serviceConfig = [
                $config[0],  // service
                $config[1],  // method
                $config[2] ?? []  // args
            ];
        } else {
            throw new \InvalidArgumentException("Invalid step configuration for '{$key}'");
        }

        // Store step with default settings
        $this->pipelineSteps[$key] = [
            'config' => $serviceConfig,
            'on_failure' => 'continue', // default: continue on failure
            'depends_on' => null, // will be set to previous steps by default
        ];

        $this->currentStepKey = $key;

        return $this;
    }

    /**
     * Stop entire pipeline if this step fails
     *
     * @return $this
     */
    public function stopOnFailure(): self
    {
        if ($this->currentStepKey) {
            $this->pipelineSteps[$this->currentStepKey]['on_failure'] = 'stop';
        }
        return $this;
    }

    /**
     * Skip dependent steps if this step fails
     *
     * @return $this
     */
    public function skipOnFailure(): self
    {
        if ($this->currentStepKey) {
            $this->pipelineSteps[$this->currentStepKey]['on_failure'] = 'skip';
        }
        return $this;
    }

    /**
     * Continue even if this step fails
     *
     * @return $this
     */
    public function continueOnFailure(): self
    {
        if ($this->currentStepKey) {
            $this->pipelineSteps[$this->currentStepKey]['on_failure'] = 'continue';
        }
        return $this;
    }

    /**
     * Set explicit dependencies for current step
     *
     * @param string ...$dependencies Step keys this step depends on
     * @return $this
     */
    public function dependsOn(string ...$dependencies): self
    {
        if ($this->currentStepKey) {
            $this->pipelineSteps[$this->currentStepKey]['depends_on'] = $dependencies;
        }
        return $this;
    }

    /**
     * Execute the pipeline
     *
     * @return array Results keyed by step names
     * @throws \Exception
     */
    public function execute(): array
    {
        if (!$this->isPipeline) {
            throw new \Exception("execute() can only be called on pipeline instances");
        }

        // Convert pipeline to services array
        $services = $this->buildServicesFromPipeline();

        // Execute using run() method
        return self::run($services);
    }

    /**
     * Convert pipeline steps to services array format
     *
     * @return array
     */
    protected function buildServicesFromPipeline(): array
    {
        $services = [];
        $previousSteps = [];

        foreach ($this->pipelineSteps as $key => $step) {
            $config = $step['config'];

            // Parse service config
            if (count($config) >= 2) {
                $service = $config[0];
                $method = $config[1];
                $args = $config[2] ?? [];
            } else {
                throw new \InvalidArgumentException("Invalid step configuration for '{$key}'");
            }

            // Build service entry
            $serviceEntry = [
                'service' => $service,
                'method' => $method,
                'args' => $args,
                'on_failure' => $step['on_failure'],
            ];

            // Handle dependencies
            if ($step['depends_on'] !== null) {
                // Explicit dependencies
                $serviceEntry['depends_on'] = $step['depends_on'];
            } else {
                // Implicit dependencies: all previous steps (for closures to access results)
                $serviceEntry['depends_on'] = $previousSteps;
            }

            $services[$key] = $serviceEntry;
            $previousSteps[] = $key;
        }

        return $services;
    }

    /**
     * Execute batch service calls with dependency resolution (Array API)
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
