<?php

namespace RemoteEloquent\Client\Serializers;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

/**
 * Parameter Serializer
 *
 * Converts PHP values to JSON-safe format for transmission to remote backend.
 * Handles special types like Closures, DateTime objects, and complex data structures.
 */
class ParameterSerializer
{
    /**
     * Serialize an array of parameters
     *
     * @param array $parameters
     * @return array
     */
    public static function serialize(array $parameters): array
    {
        return array_map([static::class, 'serializeValue'], $parameters);
    }

    /**
     * Serialize a single value
     *
     * @param mixed $value
     * @return mixed
     */
    public static function serializeValue($value)
    {
        // Null values
        if ($value === null) {
            return null;
        }

        // Scalar values (string, int, float, bool)
        if (is_scalar($value)) {
            return $value;
        }

        // DateTime instances
        if ($value instanceof DateTimeInterface) {
            return static::serializeDateTime($value);
        }

        // Closures (nested queries)
        if ($value instanceof Closure) {
            return static::serializeClosure($value);
        }

        // Arrayable objects (Collections, Models, etc.)
        if ($value instanceof Arrayable) {
            return static::serializeValue($value->toArray());
        }

        // Arrays
        if (is_array($value)) {
            return array_map([static::class, 'serializeValue'], $value);
        }

        // Objects with __toString
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        // Default: try to convert to array or string
        if (is_object($value)) {
            return [
                '__type__' => 'Object',
                'class' => get_class($value),
                'value' => method_exists($value, 'toArray') ? $value->toArray() : (string) $value,
            ];
        }

        return $value;
    }

    /**
     * Serialize DateTime instance
     *
     * @param DateTimeInterface $dateTime
     * @return array
     */
    protected static function serializeDateTime(DateTimeInterface $dateTime): array
    {
        return [
            '__type__' => 'DateTime',
            'value' => $dateTime->format('Y-m-d H:i:s'),
            'timezone' => $dateTime->getTimezone()->getName(),
        ];
    }

    /**
     * Serialize Closure by capturing its operations
     *
     * This creates a fake query builder, runs the closure on it,
     * and captures all the method calls for later reconstruction.
     *
     * @param Closure $closure
     * @return array
     */
    protected static function serializeClosure(Closure $closure): array
    {
        // Create a builder that captures method calls
        $captureBuilder = new ClosureCaptureBuilder();

        // Execute the closure with the capture builder
        $closure($captureBuilder);

        return [
            '__type__' => 'Closure',
            'chain' => $captureBuilder->getCapturedChain(),
        ];
    }
}

/**
 * Closure Capture Builder
 *
 * A fake query builder that captures all method calls made inside a closure.
 * This allows us to serialize closure operations without executing them.
 */
class ClosureCaptureBuilder
{
    /**
     * Captured method chain
     *
     * @var array
     */
    protected array $chain = [];

    /**
     * Capture any method call
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     */
    public function __call(string $method, array $parameters)
    {
        $this->chain[] = [
            'method' => $method,
            'parameters' => ParameterSerializer::serialize($parameters),
        ];

        return $this;
    }

    /**
     * Get the captured method chain
     *
     * @return array
     */
    public function getCapturedChain(): array
    {
        return $this->chain;
    }

    /**
     * Allow property access (returns $this for chaining)
     *
     * @param string $name
     * @return $this
     */
    public function __get(string $name)
    {
        return $this;
    }
}
