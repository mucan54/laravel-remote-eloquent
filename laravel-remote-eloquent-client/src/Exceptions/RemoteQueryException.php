<?php

namespace RemoteEloquent\Client\Exceptions;

use Exception;
use Throwable;

/**
 * Remote Query Exception
 *
 * Thrown when a remote query fails to execute on the backend.
 */
class RemoteQueryException extends Exception
{
    /**
     * Additional context about the error
     *
     * @var array
     */
    protected array $context;

    /**
     * HTTP status code
     *
     * @var int|null
     */
    protected ?int $statusCode;

    /**
     * Create a new exception instance
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $context
     * @param int|null $statusCode
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        ?int $statusCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->statusCode = $statusCode;
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

    /**
     * Get HTTP status code
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Create exception from HTTP response
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param array $context
     * @return static
     */
    public static function fromResponse($response, array $context = []): static
    {
        $body = $response->json();

        return new static(
            $body['error'] ?? $body['message'] ?? 'Unknown error occurred',
            0,
            null,
            array_merge($context, [
                'status_code' => $response->status(),
                'response_body' => $body,
            ]),
            $response->status()
        );
    }

    /**
     * Create exception for network error
     *
     * @param string $message
     * @param array $context
     * @return static
     */
    public static function networkError(string $message = 'Network error occurred', array $context = []): static
    {
        return new static($message, 0, null, $context, 503);
    }

    /**
     * Create exception for timeout
     *
     * @param array $context
     * @return static
     */
    public static function timeout(array $context = []): static
    {
        return new static('Request timeout', 0, null, $context, 504);
    }
}
