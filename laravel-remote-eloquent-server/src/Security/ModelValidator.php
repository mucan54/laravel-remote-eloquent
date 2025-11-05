<?php

namespace RemoteEloquent\Server\Security;

use Illuminate\Database\Eloquent\Model;
use RemoteEloquent\Server\Exceptions\SecurityException;

/**
 * Model Validator
 *
 * Ensures only whitelisted models can be queried via remote queries.
 * This is a critical security layer that prevents unauthorized model access.
 */
class ModelValidator
{
    /**
     * Validate and resolve model class
     *
     * @param string $modelName
     * @return string Full model class name
     * @throws SecurityException
     */
    public function validate(string $modelName): string
    {
        // Resolve full class name
        $modelClass = $this->resolveModelClass($modelName);

        // Check if class exists
        if (!class_exists($modelClass)) {
            throw new SecurityException("Model '{$modelName}' not found", 404);
        }

        // Check if it's an Eloquent model
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new SecurityException("'{$modelName}' is not an Eloquent model", 400);
        }

        // Check if model is allowed
        if (!$this->isAllowed($modelClass)) {
            throw new SecurityException("Model '{$modelName}' is not allowed", 403);
        }

        return $modelClass;
    }

    /**
     * Resolve model class name from short name
     *
     * @param string $modelName
     * @return string
     */
    protected function resolveModelClass(string $modelName): string
    {
        // If already fully qualified
        if (class_exists($modelName)) {
            return $modelName;
        }

        // Try common namespaces
        $namespaces = config('remote-eloquent.model_namespaces', [
            'App\\Models\\',
            'App\\',
        ]);

        foreach ($namespaces as $namespace) {
            $class = $namespace . $modelName;
            if (class_exists($class)) {
                return $class;
            }
        }

        // Return as-is if not found (will fail validation)
        return $modelName;
    }

    /**
     * Check if model is allowed
     *
     * @param string $modelClass
     * @return bool
     */
    protected function isAllowed(string $modelClass): bool
    {
        $strategy = config('remote-eloquent.security.strategy', 'whitelist');

        if ($strategy === 'whitelist') {
            return $this->checkWhitelist($modelClass);
        }

        if ($strategy === 'blacklist') {
            return $this->checkBlacklist($modelClass);
        }

        if ($strategy === 'trait') {
            return $this->checkTrait($modelClass);
        }

        // Default: deny all
        return false;
    }

    /**
     * Check whitelist strategy
     *
     * @param string $modelClass
     * @return bool
     */
    protected function checkWhitelist(string $modelClass): bool
    {
        $whitelist = config('remote-eloquent.allowed_models', []);

        if (empty($whitelist)) {
            // If no whitelist configured, deny all
            return false;
        }

        foreach ($whitelist as $allowed) {
            if ($this->matchesPattern($modelClass, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check blacklist strategy
     *
     * @param string $modelClass
     * @return bool
     */
    protected function checkBlacklist(string $modelClass): bool
    {
        $blacklist = config('remote-eloquent.blocked_models', []);

        foreach ($blacklist as $blocked) {
            if ($this->matchesPattern($modelClass, $blocked)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check trait strategy
     *
     * @param string $modelClass
     * @return bool
     */
    protected function checkTrait(string $modelClass): bool
    {
        $requiredTrait = config('remote-eloquent.security.required_trait', 'RemoteEloquent\\Server\\Traits\\RemoteQueryable');

        if (!trait_exists($requiredTrait)) {
            return false;
        }

        $traits = class_uses_recursive($modelClass);

        return in_array($requiredTrait, $traits, true);
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

        // Wildcard match (e.g., "App\Models\*")
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
