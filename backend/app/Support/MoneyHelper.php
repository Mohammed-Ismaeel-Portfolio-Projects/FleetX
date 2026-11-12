<?php

namespace App\Support;

/**
 * Money Helper
 * 
 * Provides money formatting and calculation utilities.
 * All monetary values are stored in cents (integer) to avoid floating-point errors.
 */
class MoneyHelper
{
    /**
     * Format amount to currency string.
     */
    public static function format(int $cents, string $currency = 'USD', string $locale = 'en_US'): string
    {
        $dollars = $cents / 100;
        
        return number_format($dollars, 2);
    }

    /**
     * Convert dollars to cents.
     */
    public static function toCents(float $dollars): int
    {
        return (int) round($dollars * 100);
    }

    /**
     * Convert cents to dollars.
     */
    public static function toDollars(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Add two monetary amounts.
     */
    public static function add(int $cents1, int $cents2): int
    {
        return $cents1 + $cents2;
    }

    /**
     * Subtract two monetary amounts.
     */
    public static function subtract(int $cents1, int $cents2): int
    {
        return $cents1 - $cents2;
    }

    /**
     * Multiply monetary amount by factor.
     */
    public static function multiply(int $cents, float $factor): int
    {
        return (int) round($cents * $factor);
    }

    /**
     * Divide monetary amount by divisor.
     */
    public static function divide(int $cents, float $divisor): int
    {
        return (int) round($cents / $divisor);
    }
}
