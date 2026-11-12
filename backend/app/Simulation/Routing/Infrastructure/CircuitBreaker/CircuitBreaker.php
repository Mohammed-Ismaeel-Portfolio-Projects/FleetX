<?php

namespace App\Simulation\Routing\Infrastructure\CircuitBreaker;

use App\Simulation\Routing\Infrastructure\Exceptions\CircuitBreakerOpenException;

/**
 * CircuitBreaker.
 * Circuit breaker pattern for routing providers.
 * 
 * Responsibilities:
 * - Open state
 * - Closed state
 * - Half-open state
 * - Failure threshold
 * - Recovery timeout
 */
class CircuitBreaker
{
    /**
     * Circuit breaker states.
     */
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Current state.
     * @var string
     */
    private string $state = self::STATE_CLOSED;

    /**
     * Failure count.
     * @var int
     */
    private int $failureCount = 0;

    /**
     * Success count.
     * @var int
     */
    private int $successCount = 0;

    /**
     * Failure threshold.
     * @var int
     */
    private int $failureThreshold = 5;

    /**
     * Success threshold for recovery.
     * @var int
     */
    private int $successThreshold = 2;

    /**
     * Recovery timeout in seconds.
     * @var int
     */
    private int $recoveryTimeout = 60;

    /**
     * Last failure time.
     * @var float
     */
    private float $lastFailureTime = 0;

    /**
     * Last state change time.
     * @var float
     */
    private float $lastStateChangeTime = 0;

    /**
     * Constructor.
     * 
     * @param int $failureThreshold Failure threshold
     * @param int $successThreshold Success threshold for recovery
     * @param int $recoveryTimeout Recovery timeout in seconds
     */
    public function __construct(
        int $failureThreshold = 5,
        int $successThreshold = 2,
        int $recoveryTimeout = 60,
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->successThreshold = $successThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
    }

    /**
     * Execute operation with circuit breaker.
     * 
     * @param callable $operation The operation to execute
     * @return mixed The operation result
     * @throws CircuitBreakerOpenException
     */
    public function execute(callable $operation)
    {
        if ($this->isOpen()) {
            $this->checkRecovery();
        }

        if ($this->isOpen()) {
            throw new CircuitBreakerOpenException('Circuit breaker is open');
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Check if circuit breaker is open.
     * 
     * @return bool True if open, false otherwise
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Check if circuit breaker is closed.
     * 
     * @return bool True if closed, false otherwise
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Check if circuit breaker is half-open.
     * 
     * @return bool True if half-open, false otherwise
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Get current state.
     * 
     * @return string The state
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Record success.
     */
    private function recordSuccess(): void
    {
        $this->successCount++;

        if ($this->state === self::STATE_HALF_OPEN) {
            if ($this->successCount >= $this->successThreshold) {
                $this->transitionToClosed();
            }
        } else {
            $this->failureCount = max(0, $this->failureCount - 1);
        }
    }

    /**
     * Record failure.
     */
    private function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);
        $this->successCount = 0;

        if ($this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    /**
     * Transition to open state.
     */
    private function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->lastStateChangeTime = microtime(true);
    }

    /**
     * Transition to closed state.
     */
    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->lastStateChangeTime = microtime(true);
    }

    /**
     * Transition to half-open state.
     */
    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->successCount = 0;
        $this->lastStateChangeTime = microtime(true);
    }

    /**
     * Check if recovery is possible.
     */
    private function checkRecovery(): void
    {
        if ($this->state === self::STATE_OPEN) {
            $elapsed = microtime(true) - $this->lastStateChangeTime;
            if ($elapsed >= $this->recoveryTimeout) {
                $this->transitionToHalfOpen();
            }
        }
    }

    /**
     * Reset circuit breaker.
     */
    public function reset(): void
    {
        $this->transitionToClosed();
    }

    /**
     * Get failure count.
     * 
     * @return int The failure count
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get success count.
     * 
     * @return int The success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get failure threshold.
     * 
     * @return int The threshold
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Set failure threshold.
     * 
     * @param int $threshold The threshold
     */
    public function setFailureThreshold(int $threshold): void
    {
        $this->failureThreshold = $threshold;
    }

    /**
     * Get success threshold.
     * 
     * @return int The threshold
     */
    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    /**
     * Set success threshold.
     * 
     * @param int $threshold The threshold
     */
    public function setSuccessThreshold(int $threshold): void
    {
        $this->successThreshold = $threshold;
    }

    /**
     * Get recovery timeout.
     * 
     * @return int The timeout in seconds
     */
    public function getRecoveryTimeout(): int
    {
        return $this->recoveryTimeout;
    }

    /**
     * Set recovery timeout.
     * 
     * @param int $timeout The timeout in seconds
     */
    public function setRecoveryTimeout(int $timeout): void
    {
        $this->recoveryTimeout = $timeout;
    }
}
