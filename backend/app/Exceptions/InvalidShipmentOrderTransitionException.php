<?php

namespace App\Exceptions;

/**
 * Thrown when a Shipment Order status transition is not allowed by the lifecycle map.
 */
class InvalidShipmentOrderTransitionException extends DomainException
{
    public function __construct(string $from, string $to, array $context = [])
    {
        parent::__construct(
            "Cannot transition shipment order from '{$from}' to '{$to}'",
            422,
            array_merge(['from' => $from, 'to' => $to], $context)
        );
    }
}
