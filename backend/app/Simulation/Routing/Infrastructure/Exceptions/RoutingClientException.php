<?php

namespace App\Simulation\Routing\Infrastructure\Exceptions;

use Exception;

/**
 * RoutingClientException.
 * Thrown when routing client encounters an error.
 */
class RoutingClientException extends Exception
{
    public function __construct(
        string $message = 'Routing client error',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $providerName = null,
        private ?int $statusCode = null,
        private ?array $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get provider name.
     * 
     * @return string|null The provider name
     */
    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * Get status code.
     * 
     * @return int|null The status code
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get response body.
     * 
     * @return array|null The response body
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Create from HTTP error.
     * 
     * @param string $providerName The provider name
     * @param int $statusCode The status code
     * @param array $responseBody The response body
     * @return self The exception
     */
    public static function fromHttpError(
        string $providerName,
        int $statusCode,
        array $responseBody,
    ): self {
        return new self(
            message: "HTTP error from provider '{$providerName}': {$statusCode}",
            providerName: $providerName,
            statusCode: $statusCode,
            responseBody: $responseBody,
        );
    }
}
