<?php

namespace App\Support;

/**
 * String Helper
 * 
 * Provides string manipulation utilities.
 */
class StringHelper
{
    /**
     * Generate slug from string.
     */
    public static function slug(string $string): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }

    /**
     * Truncate string to specified length.
     */
    public static function truncate(string $string, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length) . $suffix;
    }

    /**
     * Generate random string.
     */
    public static function random(int $length = 16): string
    {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 1, $length);
    }

    /**
     * Mask sensitive data (e.g., email, phone).
     */
    public static function mask(string $string, int $visibleChars = 4, string $maskChar = '*'): string
    {
        $length = strlen($string);
        
        if ($length <= $visibleChars) {
            return $string;
        }

        $visible = substr($string, 0, $visibleChars);
        $masked = str_repeat($maskChar, $length - $visibleChars);

        return $visible . $masked;
    }

    /**
     * Convert camel case to snake case.
     */
    public static function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($string)));
    }

    /**
     * Convert snake case to camel case.
     */
    public static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }
}
