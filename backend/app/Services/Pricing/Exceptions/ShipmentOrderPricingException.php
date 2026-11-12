<?php

declare(strict_types=1);

namespace App\Services\Pricing\Exceptions;

use RuntimeException;

class ShipmentOrderPricingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }
}
