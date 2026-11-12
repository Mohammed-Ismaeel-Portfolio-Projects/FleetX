<?php

namespace App\Exceptions;

use Exception;

class GeocodingRateLimitException extends Exception
{
    public function __construct(string $message = 'Geocoding provider rate limit exceeded.', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
