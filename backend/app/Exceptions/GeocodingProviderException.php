<?php

namespace App\Exceptions;

use Exception;

class GeocodingProviderException extends Exception
{
    public function __construct(string $message = 'Geocoding provider is unavailable.', int $code = 503)
    {
        parent::__construct($message, $code);
    }
}
