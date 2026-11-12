<?php

namespace App\Exceptions;

/**
 * Domain Exception
 * 
 * Exception thrown when a domain invariant is violated.
 * Used for domain logic errors that should not occur.
 */
class DomainException extends BaseException
{
    /**
     * Create a new domain exception instance.
     */
    public function __construct(string $message = 'Domain invariant violated', int $code = 500, array $context = [])
    {
        parent::__construct($message, $code, null, $context);
    }
}
