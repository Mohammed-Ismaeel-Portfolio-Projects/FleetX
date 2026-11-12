<?php

declare(strict_types=1);

namespace App\Services\Pricing\Exceptions;

use RuntimeException;

class ShipmentFinalPricingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
