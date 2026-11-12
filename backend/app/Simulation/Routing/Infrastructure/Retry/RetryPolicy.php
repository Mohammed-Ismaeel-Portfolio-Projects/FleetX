<?php

namespace App\Simulation\Routing\Infrastructure\Retry;

use App\Simulation\Routing\Infrastructure\Exceptions\RetryExhaustedException;

/**
 * RetryPolicy.
 * Retry policy for routing provider calls.
 * 
 * Responsibilities:
 * - Retry strategies
 * - Exponential backoff
 * - Maximum attempts
 * - Timeout policy
 */
class RetryPolicy
{
    /**
     * Retry strategies.
     */
    public const STRATEGY_FIXED = 'fixed';
    public const STRATEGY_EXPONENTIAL = 'exponential';
    public const STRATEGY_LINEAR = 'linear';

    /**
     * Maximum retry attempts.
     * @var int
     */
    private int $maxAttempts;

    /**
     * Retry strategy.
     * @var string
     */
    private string $strategy;

    /**
     * Initial delay in milliseconds.
     * @var int
     */
    private int $initialDelay;

    /**
     * Maximum delay in milliseconds.
     * @var int
     */
    private int $maxDelay;

    /**
     * Backoff multiplier.
     * @var float
     */
    private float $backoffMultiplier;

    /**
     * Retryable exceptions.
     * @var array<string>
     */
    private array $retryableExceptions;

    /**
     * Constructor.
     * 
     * @param int $maxAttempts Maximum retry attempts
     * @param string $strategy Retry strategy
     * @param int $initialDelay Initial delay in milliseconds
     * @param int $maxDelay Maximum delay in milliseconds
     * @param float $backoffMultiplier Backoff multiplier
     * @param array $retryableExceptions Retryable exception classes
     */
    public function __construct(
        int $maxAttempts = 3,
        string $strategy = self::STRATEGY_EXPONENTIAL,
        int $initialDelay = 1000,
        int $maxDelay = 30000,
        float $backoffMultiplier = 2.0,
        array $retryableExceptions = [],
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->strategy = $strategy;
        $this->initialDelay = $initialDelay;
        $this->maxDelay = $maxDelay;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->retryableExceptions = $retryableExceptions;
    }

    /**
     * Execute operation with retry policy.
     * 
     * @param callable $operation The operation to execute
     * @return mixed The operation result
     * @throws RetryExhaustedException
     */
    public function execute(callable $operation)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;

                if (!$this->isRetryable($e)) {
                    throw $e;
                }

                $attempt++;

                if ($attempt >= $this->maxAttempts) {
                    break;
                }

                $delay = $this->calculateDelay($attempt);
                $this->wait($delay);
            }
        }

        throw new RetryExhaustedException(
            "Operation failed after {$this->maxAttempts} attempts",
            0,
            $lastException
        );
    }

    /**
     * Calculate delay for retry attempt.
     * 
     * @param int $attempt The attempt number
     * @return int The delay in milliseconds
     */
    private function calculateDelay(int $attempt): int
    {
        return match($this->strategy) {
            self::STRATEGY_FIXED => $this->initialDelay,
            self::STRATEGY_EXPONENTIAL => min(
                $this->initialDelay * pow($this->backoffMultiplier, $attempt - 1),
                $this->maxDelay
            ),
            self::STRATEGY_LINEAR => min(
                $this->initialDelay * $attempt,
                $this->maxDelay
            ),
            default => $this->initialDelay,
        };
    }

    /**
     * Check if exception is retryable.
     * 
     * @param \Exception $exception The exception
     * @return bool True if retryable, false otherwise
     */
    private function isRetryable(\Exception $exception): bool
    {
        if (empty($this->retryableExceptions)) {
            return true;
        }

        foreach ($this->retryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wait for specified delay.
     * 
     * @param int $delay The delay in milliseconds
     */
    private function wait(int $delay): void
    {
        usleep($delay * 1000);
    }

    /**
     * Get max attempts.
     * 
     * @return int The max attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Set max attempts.
     * 
     * @param int $maxAttempts The max attempts
     */
    public function setMaxAttempts(int $maxAttempts): void
    {
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Get strategy.
     * 
     * @return string The strategy
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * Set strategy.
     * 
     * @param string $strategy The strategy
     */
    public function setStrategy(string $strategy): void
    {
        $this->strategy = $strategy;
    }

    /**
     * Get initial delay.
     * 
     * @return int The initial delay in milliseconds
     */
    public function getInitialDelay(): int
    {
        return $this->initialDelay;
    }

    /**
     * Set initial delay.
     * 
     * @param int $delay The delay in milliseconds
     */
    public function setInitialDelay(int $delay): void
    {
        $this->initialDelay = $delay;
    }

    /**
     * Get max delay.
     * 
     * @return int The max delay in milliseconds
     */
    public function getMaxDelay(): int
    {
        return $this->maxDelay;
    }

    /**
     * Set max delay.
     * 
     * @param int $delay The delay in milliseconds
     */
    public function setMaxDelay(int $delay): void
    {
        $this->maxDelay = $delay;
    }

    /**
     * Get backoff multiplier.
     * 
     * @return float The multiplier
     */
    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }

    /**
     * Set backoff multiplier.
     * 
     * @param float $multiplier The multiplier
     */
    public function setBackoffMultiplier(float $multiplier): void
    {
        $this->backoffMultiplier = $multiplier;
    }

    /**
     * Add retryable exception.
     * 
     * @param string $exceptionClass The exception class
     */
    public function addRetryableException(string $exceptionClass): void
    {
        $this->retryableExceptions[] = $exceptionClass;
    }

    /**
     * Remove retryable exception.
     * 
     * @param string $exceptionClass The exception class
     */
    public function removeRetryableException(string $exceptionClass): void
    {
        $this->retryableExceptions = array_filter(
            $this->retryableExceptions,
            fn($class) => $class !== $exceptionClass
        );
    }
}
