<?php

namespace App\ValueObjects;

/**
 * Money Value Object
 * 
 * Immutable value object representing monetary values.
 * Amount is stored in cents (integer) to avoid floating-point errors.
 */
class Money
{
    public function __construct(
        public readonly int $amount, // Amount in cents
        public readonly string $currency // ISO 4217 code (e.g., USD, EUR)
    ) {}

    /**
     * Create from dollars.
     */
    public static function fromDollars(float $dollars, string $currency = 'USD'): self
    {
        return new self(
            amount: (int) round($dollars * 100),
            currency: $currency
        );
    }

    /**
     * Create from cents.
     */
    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self(
            amount: $cents,
            currency: $currency
        );
    }

    /**
     * Get amount in dollars.
     */
    public function toDollars(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get amount in cents.
     */
    public function toCents(): int
    {
        return $this->amount;
    }

    /**
     * Add another money value.
     */
    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add money with different currencies');
        }

        return new self(
            amount: $this->amount + $other->amount,
            currency: $this->currency
        );
    }

    /**
     * Subtract another money value.
     */
    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot subtract money with different currencies');
        }

        return new self(
            amount: $this->amount - $other->amount,
            currency: $this->currency
        );
    }

    /**
     * Multiply by factor.
     */
    public function multiply(float $factor): self
    {
        return new self(
            amount: (int) round($this->amount * $factor),
            currency: $this->currency
        );
    }

    /**
     * Format to string.
     */
    public function format(): string
    {
        return number_format($this->toDollars(), 2) . ' ' . $this->currency;
    }

    /**
     * Check equality with another money value.
     */
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
