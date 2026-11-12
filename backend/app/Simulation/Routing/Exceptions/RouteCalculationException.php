<?php

namespace App\Simulation\Routing\Exceptions;

use Exception;

/**
 * RouteCalculationException.
 * Thrown when route calculation fails.
 */
class RouteCalculationException extends Exception
{
    public function __construct(
        string $message = 'Route calculation failed.',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $provider = null,
        private ?array $providerError = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get provider name.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Get provider error details.
     */
    public function getProviderError(): ?array
    {
        return $this->providerError;
    }

    /**
     * Create from provider error.
     */
    public static function fromProviderError(
        string $provider,
        array $providerError,
        string $message = 'Route calculation failed.',
    ): self {
        return new self(
            message: $message,
            provider: $provider,
            providerError: $providerError,
        );
    }
}
