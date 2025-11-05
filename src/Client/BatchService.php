<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\Facades\Http;

/**
 * Batch Service
 *
 * Execute multiple service methods in a single request (client mode)
 * or execute them locally (server mode).
 *
 * Works in BOTH modes - same code everywhere!
 *
 * Usage:
 * ```php
 * // Works in both client and server modes!
 * $results = BatchService::run([
 *     'charge' => [$paymentService, 'processPayment', [1000, $token]],
 *     'email' => [$emailService, 'sendReceipt', [$userId, $orderId]],
 *     'sms' => [$smsService, 'sendConfirmation', [$phone]],
 * ]);
 *
 * $chargeId = $results['charge'];
 * $emailSent = $results['email'];
 * $smsSent = $results['sms'];
 * ```
 */
class BatchService
{
    /**
     * Execute batch service calls
     *
     * @param array $services Array of [service, method, arguments]
     * @return array
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
        // Build batch AST
        $batch = [];
        foreach ($services as $key => $item) {
            [$service, $method, $arguments] = static::parseServiceItem($item);

            $batch[$key] = [
                'service' => get_class($service),
                'method' => $method,
                'arguments' => static::serializeArguments($arguments),
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
     * Execute batch locally (server mode)
     *
     * @param array $services
     * @return array
     */
    protected static function executeLocal(array $services): array
    {
        $results = [];

        foreach ($services as $key => $item) {
            try {
                [$service, $method, $arguments] = static::parseServiceItem($item);

                // Execute method
                $results[$key] = $service->$method(...$arguments);
            } catch (\Exception $e) {
                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Parse service item
     *
     * @param mixed $item
     * @return array [service, method, arguments]
     */
    protected static function parseServiceItem($item): array
    {
        if (is_array($item) && count($item) >= 2) {
            $service = $item[0];
            $method = $item[1];
            $arguments = $item[2] ?? [];

            return [$service, $method, $arguments];
        }

        throw new \InvalidArgumentException("Invalid service item format. Expected [service, method, arguments]");
    }

    /**
     * Serialize arguments
     *
     * @param array $arguments
     * @return array
     */
    protected static function serializeArguments(array $arguments): array
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
