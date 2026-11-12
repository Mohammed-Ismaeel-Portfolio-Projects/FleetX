<?php

declare(strict_types=1);

namespace App\Services\Shipment\Exceptions;

use RuntimeException;

class ShipmentOperationalActualsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
