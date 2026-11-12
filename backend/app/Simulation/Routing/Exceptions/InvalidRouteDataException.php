<?php

namespace App\Simulation\Routing\Exceptions;

use Exception;

/**
 * InvalidRouteDataException.
 * Thrown when route data is invalid.
 */
class InvalidRouteDataException extends Exception
{
    public function __construct(
        string $message = 'Invalid route data.',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $invalidFields = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get invalid fields.
     */
    public function getInvalidFields(): ?array
    {
        return $this->invalidFields;
    }

    /**
     * Create from invalid fields.
     */
    public static function fromInvalidFields(array $fields): self
    {
        return new self(
            message: 'Invalid route data: ' . implode(', ', $fields),
            invalidFields: $fields,
        );
    }
}
