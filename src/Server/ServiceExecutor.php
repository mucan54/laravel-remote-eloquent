<?php

namespace RemoteEloquent\Server;

/**
 * Service Executor
 *
 * Executes service methods safely on the server.
 * Validates service class and method before execution.
 */
class ServiceExecutor
{
    /**
     * Execute service method from AST
     *
     * @param array $ast
     * @return mixed
     * @throws \Exception
     */
    public function execute(array $ast)
    {
        // Validate AST structure
        $this->validateAST($ast);

        // Validate service class
        $serviceClass = $this->validateService($ast['service']);

        // Validate method
        $this->validateMethod($serviceClass, $ast['method']);

        // Instantiate service
        $service = $this->instantiateService($serviceClass);

        // Deserialize arguments
        $arguments = $this->deserializeArguments($ast['arguments'] ?? []);

        // Execute method
        $result = $service->{$ast['method']}(...$arguments);

        // Serialize result
        return $this->serializeResult($result);
    }

    /**
     * Validate AST structure
     *
     * @param array $ast
     * @return void
     * @throws \Exception
     */
    protected function validateAST(array $ast): void
    {
        if (!isset($ast['service']) || !is_string($ast['service'])) {
            throw new \Exception("AST must contain a valid 'service' field");
        }

        if (!isset($ast['method']) || !is_string($ast['method'])) {
            throw new \Exception("AST must contain a valid 'method' field");
        }
    }

    /**
     * Validate service class
     *
     * @param string $serviceClass
     * @return string
     * @throws \Exception
     */
    protected function validateService(string $serviceClass): string
    {
        // Check if class exists
        if (!class_exists($serviceClass)) {
            throw new \Exception("Service class '{$serviceClass}' not found");
        }

        // Check whitelist
        $allowed = config('remote-eloquent.allowed_services', []);

        if (empty($allowed)) {
            throw new \Exception("Service whitelist is empty. Configure allowed_services.");
        }

        $matched = false;
        foreach ($allowed as $allowedPattern) {
            if ($this->matchesPattern($serviceClass, $allowedPattern)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw new \Exception("Service '{$serviceClass}' is not allowed");
        }

        return $serviceClass;
    }

    /**
     * Validate method is allowed
     *
     * @param string $serviceClass
     * @param string $method
     * @return void
     * @throws \Exception
     */
    protected function validateMethod(string $serviceClass, string $method): void
    {
        // Check if method exists
        if (!method_exists($serviceClass, $method)) {
            throw new \Exception("Method '{$method}' does not exist in '{$serviceClass}'");
        }

        // Check if method is public
        $reflection = new \ReflectionMethod($serviceClass, $method);
        if (!$reflection->isPublic()) {
            throw new \Exception("Method '{$method}' must be public");
        }

        // Check if method is in remoteMethods array (if trait is used)
        $service = new $serviceClass();
        if (method_exists($service, 'getRemoteMethods')) {
            $remoteMethods = $service->getRemoteMethods();
            if (!in_array($method, $remoteMethods)) {
                throw new \Exception("Method '{$method}' is not marked as remote in \$remoteMethods array");
            }
        }
    }

    /**
     * Instantiate service class
     *
     * @param string $serviceClass
     * @return object
     */
    protected function instantiateService(string $serviceClass): object
    {
        // Use Laravel container to resolve dependencies
        return app($serviceClass);
    }

    /**
     * Deserialize arguments
     *
     * @param array $arguments
     * @return array
     */
    protected function deserializeArguments(array $arguments): array
    {
        return array_map(function ($arg) {
            if (is_array($arg) && isset($arg['__type__'])) {
                return match($arg['__type__']) {
                    'DateTime' => \Illuminate\Support\Carbon::parse($arg['value'], $arg['timezone'] ?? 'UTC'),
                    'Object' => (object) $arg['data'],
                    default => $arg,
                };
            }

            return $arg;
        }, $arguments);
    }

    /**
     * Serialize result
     *
     * @param mixed $result
     * @return mixed
     */
    protected function serializeResult($result)
    {
        if ($result === null) {
            return null;
        }

        if (is_scalar($result)) {
            return $result;
        }

        if ($result instanceof \DateTimeInterface) {
            return [
                '__type__' => 'DateTime',
                'value' => $result->format('Y-m-d H:i:s'),
                'timezone' => $result->getTimezone()->getName(),
            ];
        }

        if ($result instanceof \Illuminate\Support\Collection) {
            return [
                '__type__' => 'Collection',
                'data' => $result->toArray(),
            ];
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        if (is_array($result)) {
            return array_map([$this, 'serializeResult'], $result);
        }

        return $result;
    }

    /**
     * Check if class matches pattern
     *
     * @param string $class
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $class, string $pattern): bool
    {
        // Exact match
        if ($class === $pattern) {
            return true;
        }

        // Wildcard match (e.g., "App\Services\*")
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $class) === 1;
        }

        // Short name match
        if (class_basename($class) === $pattern) {
            return true;
        }

        return false;
    }
}
