<?php

namespace RemoteEloquent\Server\Exceptions;

use Exception;

/**
 * Security Exception
 *
 * Thrown when a security validation fails.
 */
class SecurityException extends Exception
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode;

    /**
     * Create a new exception instance
     *
     * @param string $message
     * @param int $statusCode
     */
    public function __construct(string $message, int $statusCode = 403)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
