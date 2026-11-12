<?php

namespace App\Exceptions;

/**
 * Thrown when a Shipment status transition is not allowed by the transition map.
 */
class InvalidShipmentTransitionException extends DomainException
{
    public function __construct(string $from, string $to, array $context = [])
    {
        parent::__construct(
            "Cannot transition shipment from '{$from}' to '{$to}'",
            422,
            array_merge(['from' => $from, 'to' => $to], $context)
        );
    }
}
