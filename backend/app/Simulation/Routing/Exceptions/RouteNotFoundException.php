<?php

namespace App\Simulation\Routing\Exceptions;

use Exception;

/**
 * RouteNotFoundException.
 * Thrown when a route is not found.
 */
class RouteNotFoundException extends Exception
{
    public function __construct(
        string $message = 'Route not found.',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?string $routeId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get route ID.
     */
    public function getRouteId(): ?string
    {
        return $this->routeId;
    }

    /**
     * Create from route ID.
     */
    public static function fromRouteId(string $routeId): self
    {
        return new self(
            message: "Route with ID '{$routeId}' not found.",
            routeId: $routeId,
        );
    }
}
