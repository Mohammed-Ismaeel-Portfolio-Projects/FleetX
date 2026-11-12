<?php

namespace App\Simulation\Routing\Infrastructure\Exceptions;

use Exception;

/**
 * CircuitBreakerOpenException.
 * Thrown when circuit breaker is open.
 */
class CircuitBreakerOpenException extends Exception
{
    public function __construct(
        string $message = 'Circuit breaker is open',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $state = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get state.
     * 
     * @return string|null The circuit breaker state
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * Create from state.
     * 
     * @param string $state The circuit breaker state
     * @return self The exception
     */
    public static function fromState(string $state): self
    {
        return new self(
            message: "Circuit breaker is in state: {$state}",
            state: $state,
        );
    }
}
