<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Date Helper
 * 
 * Provides date formatting and manipulation utilities.
 */
class DateHelper
{
    /**
     * Format date to ISO 8601 string.
     */
    public static function toIso(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Parse ISO 8601 string to Carbon instance.
     */
    public static function fromIso(string $isoString): Carbon
    {
        return Carbon::parse($isoString);
    }

    /**
     * Format date to display format.
     */
    public static function toDisplay(\DateTimeInterface $date, string $format = 'Y-m-d H:i:s'): string
    {
        return $date->format($format);
    }

    /**
     * Get current UTC time.
     */
    public static function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    /**
     * Calculate duration between two dates in hours.
     */
    public static function durationInHours(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        return $end->diffInMinutes($start) / 60;
    }

    /**
     * Calculate duration between two dates in minutes.
     */
    public static function durationInMinutes(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return $end->diffInMinutes($start);
    }
}
