<?php

namespace RemoteEloquent\Server\Deserializers;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Parameter Deserializer
 *
 * Reconstructs PHP values from serialized format received from client.
 * Handles special types like Closures, DateTime objects, and complex data structures.
 */
class ParameterDeserializer
{
    /**
     * Deserialize an array of parameters
     *
     * @param array $parameters
     * @return array
     */
    public function deserialize(array $parameters): array
    {
        return array_map([$this, 'deserializeValue'], $parameters);
    }

    /**
     * Deserialize a single value
     *
     * @param mixed $value
     * @return mixed
     */
    public function deserializeValue($value)
    {
        // Null values
        if ($value === null) {
            return null;
        }

        // Scalar values
        if (!is_array($value)) {
            return $value;
        }

        // Check for special types (marked with __type__)
        if (isset($value['__type__'])) {
            return match ($value['__type__']) {
                'DateTime' => $this->deserializeDateTime($value),
                'Closure' => $this->deserializeClosure($value),
                'Object' => $value['value'] ?? null,
                default => $value,
            };
        }

        // Regular arrays - recursively deserialize
        return array_map([$this, 'deserializeValue'], $value);
    }

    /**
     * Deserialize DateTime from serialized format
     *
     * @param array $value
     * @return Carbon
     */
    protected function deserializeDateTime(array $value): Carbon
    {
        $timezone = $value['timezone'] ?? 'UTC';
        return Carbon::parse($value['value'], $timezone);
    }

    /**
     * Deserialize Closure by reconstructing query operations
     *
     * This creates a real closure that applies the captured method chain
     * to the query builder passed to it.
     *
     * @param array $value
     * @return Closure
     */
    protected function deserializeClosure(array $value): Closure
    {
        $chain = $value['chain'] ?? [];

        return function ($query) use ($chain) {
            foreach ($chain as $link) {
                $method = $link['method'];
                $params = $this->deserialize($link['parameters'] ?? []);

                // Apply the method to the query
                $query->$method(...$params);
            }
        };
    }
}
