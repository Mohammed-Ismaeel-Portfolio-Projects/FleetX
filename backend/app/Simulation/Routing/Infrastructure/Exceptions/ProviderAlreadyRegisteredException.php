<?php

namespace App\Simulation\Routing\Infrastructure\Exceptions;

use Exception;

/**
 * ProviderAlreadyRegisteredException.
 * Thrown when attempting to register a provider that is already registered.
 */
class ProviderAlreadyRegisteredException extends Exception
{
    public function __construct(
        string $message = 'Provider already registered',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $providerName = null,
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
     * Create from provider name.
     * 
     * @param string $providerName The provider name
     * @return self The exception
     */
    public static function fromProviderName(string $providerName): self
    {
        return new self(
            message: "Provider '{$providerName}' is already registered",
            providerName: $providerName,
        );
    }
}
