<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\Facades\Http;

/**
 * Remote Service Trait
 *
 * Allows service classes to execute methods remotely on the server.
 * Useful for services that need server-side credentials or resources.
 *
 * Usage:
 * ```php
 * class PaymentService
 * {
 *     use RemoteService;
 *
 *     // Methods in this array will execute on server
 *     protected array $remoteMethods = [
 *         'processPayment',
 *         'refundPayment',
 *     ];
 *
 *     public function processPayment($amount, $token)
 *     {
 *         // This executes on server (has Stripe secret key)
 *         // ...
 *     }
 *
 *     public function calculateTotal($items)
 *     {
 *         // This executes locally (not in $remoteMethods)
 *         // ...
 *     }
 * }
 * ```
 */
trait RemoteService
{
    /**
     * Array of methods that should execute remotely
     *
     * @var array
     */
    protected array $remoteMethods = [];

    /**
     * Intercept method calls
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments)
    {
        // Check if method should execute remotely
        if ($this->shouldExecuteRemotely($method)) {
            return $this->executeRemoteServiceMethod($method, $arguments);
        }

        // Method not found and not remote
        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    /**
     * Check if method should execute remotely
     *
     * @param string $method
     * @return bool
     */
    protected function shouldExecuteRemotely(string $method): bool
    {
        // Only in client mode
        if (config('remote-eloquent.mode') !== 'client') {
            return false;
        }

        return in_array($method, $this->remoteMethods);
    }

    /**
     * Execute method on remote server
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    protected function executeRemoteServiceMethod(string $method, array $arguments)
    {
        $serviceClass = get_class($this);

        // Build AST
        $ast = [
            'service' => $serviceClass,
            'method' => $method,
            'arguments' => $this->serializeArguments($arguments),
        ];

        // Send to server
        $apiUrl = config('remote-eloquent.api_url');
        $token = cache(config('remote-eloquent.auth.cache_key', 'remote_eloquent_token'));

        $response = Http::timeout(30)
            ->withToken($token)
            ->post("{$apiUrl}/api/remote-eloquent/service", $ast);

        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            throw new \Exception("Remote service call failed: {$error}");
        }

        $result = $response->json('data');

        return $this->deserializeResult($result);
    }

    /**
     * Serialize method arguments
     *
     * @param array $arguments
     * @return array
     */
    protected function serializeArguments(array $arguments): array
    {
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
     * Deserialize result from server
     *
     * @param mixed $result
     * @return mixed
     */
    protected function deserializeResult($result)
    {
        if (is_array($result)) {
            // Check if it's a serialized DateTime
            if (isset($result['__type__']) && $result['__type__'] === 'DateTime') {
                return \Illuminate\Support\Carbon::parse($result['value'], $result['timezone'] ?? 'UTC');
            }

            // Check if it's a collection
            if (isset($result['__type__']) && $result['__type__'] === 'Collection') {
                return collect($result['data']);
            }

            // Recursively deserialize arrays
            return array_map([$this, 'deserializeResult'], $result);
        }

        return $result;
    }

    /**
     * Get remote methods list
     *
     * @return array
     */
    public function getRemoteMethods(): array
    {
        return $this->remoteMethods;
    }

    /**
     * Check if running in remote mode
     *
     * @return bool
     */
    public function isRemoteMode(): bool
    {
        return config('remote-eloquent.mode') === 'client';
    }
}
