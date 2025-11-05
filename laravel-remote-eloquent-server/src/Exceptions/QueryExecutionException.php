<?php

namespace RemoteEloquent\Server\Exceptions;

use Exception;
use Throwable;

/**
 * Query Execution Exception
 *
 * Thrown when a query fails to execute on the server.
 */
class QueryExecutionException extends Exception
{
    /**
     * Additional context about the error
     *
     * @var array
     */
    protected array $context;

    /**
     * Create a new exception instance
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $context
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
