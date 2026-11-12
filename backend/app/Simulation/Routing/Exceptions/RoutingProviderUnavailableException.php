<?php

namespace App\Simulation\Routing\Exceptions;

use Exception;

/**
 * RoutingProviderUnavailableException.
 * Thrown when a routing provider is unavailable.
 */
class RoutingProviderUnavailableException extends Exception
{
    public function __construct(
        string $message = 'Routing provider is unavailable.',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $provider = null,
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
     * Create from provider name.
     */
    public static function fromProvider(string $provider): self
    {
        return new self(
            message: "Routing provider '{$provider}' is unavailable.",
            provider: $provider,
        );
    }
}
