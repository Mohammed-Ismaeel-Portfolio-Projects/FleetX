<?php

namespace App\Support;

use Ramsey\Uuid\Uuid;

/**
 * UUID Helper
 * 
 * Provides UUID generation utilities.
 */
class UuidHelper
{
    /**
     * Generate a UUID v4.
     */
    public static function generate(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Generate a UUID v7 (time-ordered).
     */
    public static function generateV7(): string
    {
        return Uuid::uuid7()->toString();
    }

    /**
     * Validate a UUID string.
     */
    public static function isValid(string $uuid): bool
    {
        return Uuid::isValid($uuid);
    }
}
