<?php

namespace App\Simulation\Routing\Infrastructure\Exceptions;

use Exception;

/**
 * RetryExhaustedException.
 * Thrown when retry policy is exhausted.
 */
class RetryExhaustedException extends Exception
{
    public function __construct(
        string $message = 'Retry attempts exhausted',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?int $attempts = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get attempts.
     * 
     * @return int|null The number of attempts
     */
    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    /**
     * Create from attempts.
     * 
     * @param int $attempts The number of attempts
     * @return self The exception
     */
    public static function fromAttempts(int $attempts): self
    {
        return new self(
            message: "Operation failed after {$attempts} attempts",
            attempts: $attempts,
        );
    }
}
